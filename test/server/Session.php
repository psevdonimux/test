<?php declare(strict_types=1);

namespace raklib\server;

use raklib\protocol\{ACK, ConnectedPing, ConnectedPong, ConnectionRequest, ConnectionRequestAccepted, Datagram, DisconnectionNotification, EncapsulatedPacket, MessageIdentifiers};
use raklib\protocol\{NACK, NewIncomingConnection, Packet, PacketReliability};
use raklib\RakLib;
use raklib\utils\InternetAddress;

class Session{

 public const STATE_CONNECTING = 0;
 public const STATE_CONNECTED = 1;
 public const STATE_DISCONNECTING = 2;
 public const STATE_DISCONNECTED = 3;
 public const MIN_MTU_SIZE = 400;
 private const MAX_SPLIT_SIZE = 128;
 private const MAX_SPLIT_COUNT = 4;
 private const CHANNEL_COUNT = 32;

 public static int $WINDOW_SIZE = 2048;
 private int $messageIndex = 0, $state = self::STATE_CONNECTING, $mtuSize, $id, $splitID = 0, $sendSeqNumber = 0, $windowStart, $windowEnd, $highestSeqNumberThisTick = -1, $reliableWindowStart, $reliableWindowEnd, $lastPingMeasure = 1;
 private array $sendOrderedIndex, $sendSequencedIndex, $receiveOrderedIndex, $receiveSequencedHighestIndex, $receiveOrderedPackets, $packetToSend = [], $ACKQueue = [], $NACKQueue = [], $recoveryQueue = [], $splitPackets = [], $needACK = [], $reliableWindow = [];
 private SessionManager $sessionManager;
 private InternetAddress $address;
 private float $lastUpdate, $lastPingTime = -1;
 private ?float $disconnectionTime;
 private bool $isTemporal = true, $isActive = false;
 private Datagram $sendQueue;

public function __construct(SessionManager $sessionManager, InternetAddress $address, int $clientId, int $mtuSize){
if($mtuSize < self::MIN_MTU_SIZE){
throw new \InvalidArgumentException('MTU size must be at least '. self::MIN_MTU_SIZE . ", got $mtuSize");
}
$this->sessionManager = $sessionManager;
$this->address = $address;
$this->id = $clientId;
$this->sendQueue = new Datagram();
$this->lastUpdate = microtime(true);
$this->windowStart = 0;
$this->windowEnd = self::$WINDOW_SIZE;
$this->reliableWindowStart = 0;
$this->reliableWindowEnd = self::$WINDOW_SIZE;
$this->sendOrderedIndex = array_fill(0, self::CHANNEL_COUNT, 0);
$this->sendSequencedIndex = array_fill(0, self::CHANNEL_COUNT, 0);
$this->receiveOrderedIndex = array_fill(0, self::CHANNEL_COUNT, 0);
$this->receiveSequencedHighestIndex = array_fill(0, self::CHANNEL_COUNT, 0);
$this->receiveOrderedPackets = array_fill(0, self::CHANNEL_COUNT, []);
$this->mtuSize = $mtuSize;
}
public function getAddress() : InternetAddress{
return $this->address;
}
public function getID() : int{
return $this->id;
}
public function getState() : int{
return $this->state;
}
public function isTemporal() : bool{
return $this->isTemporal;
}
public function isConnected() : bool{
return $this->state !== self::STATE_DISCONNECTING and $this->state !== self::STATE_DISCONNECTED;
}
public function update(float $time) : void{
if(!$this->isActive and ($this->lastUpdate + 10) < $time){
$this->disconnect('timeout');
return;
}
if($this->state === self::STATE_DISCONNECTING and (
(count($this->sendQueue->packets) === 0 and count($this->ACKQueue) === 0 and count($this->NACKQueue) === 0 and count($this->packetToSend) === 0 and count($this->recoveryQueue) === 0) or
$this->disconnectionTime + 10 < $time)
){
$this->close();
return;
}
$this->isActive = false;
$diff = $this->highestSeqNumberThisTick - $this->windowStart + 1;
assert($diff >= 0);
if($diff > 0){
$this->windowStart += $diff;
$this->windowEnd += $diff;
}
if(count($this->ACKQueue) > 0){
$pk = new ACK();
$pk->packets = $this->ACKQueue;
$this->sendPacket($pk);
$this->ACKQueue = [];
}
if(count($this->NACKQueue) > 0){
$pk = new NACK();
$pk->packets = $this->NACKQueue;
$this->sendPacket($pk);
$this->NACKQueue = [];
}
if(count($this->packetToSend) > 0){
$limit = 16;
foreach($this->packetToSend as $k => $pk){
$this->sendDatagram($pk);
unset($this->packetToSend[$k]);
if(--$limit <= 0){
break;
}
}
if(count($this->packetToSend) > self::$WINDOW_SIZE){
$this->packetToSend = [];
}
}
if(count($this->needACK) > 0){
foreach($this->needACK as $identifierACK => $indexes){
if(count($indexes) === 0){
unset($this->needACK[$identifierACK]);
$this->sessionManager->notifyACK($this, $identifierACK);
}
}
}
foreach($this->recoveryQueue as $seq => $pk){
if($pk->sendTime < (time() - 8)){
$this->packetToSend[] = $pk;
unset($this->recoveryQueue[$seq]);
}else{
break;
}
}
if($this->lastPingTime + 5 < $time){
$this->sendPing();
$this->lastPingTime = $time;
}
$this->sendQueue();
}
public function disconnect(string $reason = 'unknown') : void{
$this->sessionManager->removeSession($this, $reason);
}
private function sendDatagram(Datagram $datagram) : void{
if($datagram->seqNumber !== null){
unset($this->recoveryQueue[$datagram->seqNumber]);
}
$datagram->seqNumber = $this->sendSeqNumber++;
$datagram->sendTime = microtime(true);
$this->recoveryQueue[$datagram->seqNumber] = $datagram;
$this->sendPacket($datagram);
}
private function queueConnectedPacket(Packet $packet, int $reliability, int $orderChannel, int $flags = RakLib::PRIORITY_NORMAL) : void{
$packet->encode();
$encapsulated = new EncapsulatedPacket();
$encapsulated->reliability = $reliability;
$encapsulated->orderChannel = $orderChannel;
$encapsulated->buffer = $packet->getBuffer();
$this->addEncapsulatedToQueue($encapsulated, $flags);
}
private function sendPacket(Packet $packet) : void{
$this->sessionManager->sendPacket($packet, $this->address);
}
public function sendQueue() : void{
if(count($this->sendQueue->packets) > 0){
$this->sendDatagram($this->sendQueue);
$this->sendQueue = new Datagram();
}
}
private function sendPing(int $reliability = PacketReliability::UNRELIABLE) : void{
$pk = new ConnectedPing();
$pk->sendPingTime = $this->sessionManager->getRakNetTimeMS();
$this->queueConnectedPacket($pk, $reliability, 0, RakLib::PRIORITY_IMMEDIATE);
}
private function addToQueue(EncapsulatedPacket $pk, int $flags = RakLib::PRIORITY_NORMAL) : void{
$priority = $flags & 0b00000111;
if($pk->needACK and $pk->messageIndex !== null){
$this->needACK[$pk->identifierACK][$pk->messageIndex] = $pk->messageIndex;
}
$length = $this->sendQueue->length();
if($length + $pk->getTotalLength() > $this->mtuSize - 36){
$this->sendQueue();
}
if($pk->needACK){
$this->sendQueue->packets[] = clone $pk;
$pk->needACK = false;
}else{
$this->sendQueue->packets[] = $pk->toBinary();
}
if($priority === RakLib::PRIORITY_IMMEDIATE){
$this->sendQueue();
}
}
public function addEncapsulatedToQueue(EncapsulatedPacket $packet, int $flags = RakLib::PRIORITY_NORMAL) : void{
if(($packet->needACK = ($flags & RakLib::FLAG_NEED_ACK) > 0) === true){
$this->needACK[$packet->identifierACK] = [];
}
if(PacketReliability::isOrdered($packet->reliability)){
$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel]++;
}elseif(PacketReliability::isSequenced($packet->reliability)){
$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel];
$packet->sequenceIndex = $this->sendSequencedIndex[$packet->orderChannel]++;
}
$maxSize = $this->mtuSize - 60;
if(strlen($packet->buffer) > $maxSize){
$buffers = str_split($packet->buffer, $maxSize);
assert($buffers !== false);
$bufferCount = count($buffers);
$splitID = ++$this->splitID % 65536;
foreach($buffers as $count => $buffer){
$pk = new EncapsulatedPacket();
$pk->splitID = $splitID;
$pk->hasSplit = true;
$pk->splitCount = $bufferCount;
$pk->reliability = $packet->reliability;
$pk->splitIndex = $count;
$pk->buffer = $buffer;
if(PacketReliability::isReliable($pk->reliability)){
$pk->messageIndex = $this->messageIndex++;
}
$pk->sequenceIndex = $packet->sequenceIndex;
$pk->orderChannel = $packet->orderChannel;
$pk->orderIndex = $packet->orderIndex;
$this->addToQueue($pk, $flags | RakLib::PRIORITY_IMMEDIATE);
}
}else{
if(PacketReliability::isReliable($packet->reliability)){
$packet->messageIndex = $this->messageIndex++;
}
$this->addToQueue($packet, $flags);
}
}
private function handleSplit(EncapsulatedPacket $packet) : ?EncapsulatedPacket{
if(
$packet->splitCount >= self::MAX_SPLIT_SIZE or $packet->splitCount < 0 or
$packet->splitIndex >= $packet->splitCount or $packet->splitIndex < 0
){
$this->sessionManager->getLogger()->debug('Invalid split packet part from '. $this->address . ", too many parts or invalid split index (part index $packet->splitIndex, part count $packet->splitCount)");
return null;
}
if(!isset($this->splitPackets[$packet->splitID])){
if(count($this->splitPackets) >= self::MAX_SPLIT_COUNT){
$this->sessionManager->getLogger()->debug('Ignored split packet part from '. $this->address . " because reached concurrent split packet limit of " . self::MAX_SPLIT_COUNT);
return null;
}
$this->splitPackets[$packet->splitID] = array_fill(0, $packet->splitCount, null);
}elseif(count($this->splitPackets[$packet->splitID]) !== $packet->splitCount){
$this->sessionManager->getLogger()->debug("Wrong split count $packet->splitCount for split packet $packet->splitID from $this->address, expected " . count($this->splitPackets[$packet->splitID]));
return null;
}
$this->splitPackets[$packet->splitID][$packet->splitIndex] = $packet;
foreach($this->splitPackets[$packet->splitID] as $splitIndex => $part){
if($part === null){
return null;
}
}
$pk = new EncapsulatedPacket();
$pk->buffer = '';
$pk->reliability = $packet->reliability;
$pk->messageIndex = $packet->messageIndex;
$pk->sequenceIndex = $packet->sequenceIndex;
$pk->orderIndex = $packet->orderIndex;
$pk->orderChannel = $packet->orderChannel;
for($i = 0; $i < $packet->splitCount; ++$i){
$pk->buffer .= $this->splitPackets[$packet->splitID][$i]->buffer;
}
$pk->length = strlen($pk->buffer);
unset($this->splitPackets[$packet->splitID]);
return $pk;
}
private function handleEncapsulatedPacket(EncapsulatedPacket $packet) : void{
if($packet->messageIndex !== null){
if($packet->messageIndex < $this->reliableWindowStart or $packet->messageIndex > $this->reliableWindowEnd or isset($this->reliableWindow[$packet->messageIndex])){
return;
}
$this->reliableWindow[$packet->messageIndex] = true;
if($packet->messageIndex === $this->reliableWindowStart){
for(; isset($this->reliableWindow[$this->reliableWindowStart]); ++$this->reliableWindowStart){
unset($this->reliableWindow[$this->reliableWindowStart]);
++$this->reliableWindowEnd;
}
}
}
if($packet->hasSplit){
if(($packet = $this->handleSplit($packet)) === null){
return;
}
}
if(PacketReliability::isSequencedOrOrdered($packet->reliability) and ($packet->orderChannel < 0 or $packet->orderChannel >= self::CHANNEL_COUNT)){
$this->sessionManager->getLogger()->debug('Invalid packet from '. $this->address . ", bad order channel ($packet->orderChannel)");
return;
}
if(PacketReliability::isSequenced($packet->reliability)){
if($packet->sequenceIndex < $this->receiveSequencedHighestIndex[$packet->orderChannel] or $packet->orderIndex < $this->receiveOrderedIndex[$packet->orderChannel]){
return;
}
$this->receiveSequencedHighestIndex[$packet->orderChannel] = $packet->sequenceIndex + 1;
$this->handleEncapsulatedPacketRoute($packet);
}elseif(PacketReliability::isOrdered($packet->reliability)){
if($packet->orderIndex === $this->receiveOrderedIndex[$packet->orderChannel]){
$this->receiveSequencedHighestIndex[$packet->orderIndex] = 0;
$this->receiveOrderedIndex[$packet->orderChannel] = $packet->orderIndex + 1;
$this->handleEncapsulatedPacketRoute($packet);
$i = $this->receiveOrderedIndex[$packet->orderChannel];
for(; isset($this->receiveOrderedPackets[$packet->orderChannel][$i]); ++$i){
$this->handleEncapsulatedPacketRoute($this->receiveOrderedPackets[$packet->orderChannel][$i]);
unset($this->receiveOrderedPackets[$packet->orderChannel][$i]);
}
$this->receiveOrderedIndex[$packet->orderChannel] = $i;
}elseif($packet->orderIndex > $this->receiveOrderedIndex[$packet->orderChannel]){
$this->receiveOrderedPackets[$packet->orderChannel][$packet->orderIndex] = $packet;
}
}else{
$this->handleEncapsulatedPacketRoute($packet);
}
}
private function handleEncapsulatedPacketRoute(EncapsulatedPacket $packet) : void{
if($this->sessionManager === null){
return;
}
$id = ord($packet->buffer[0]);
if($id < MessageIdentifiers::ID_USER_PACKET_ENUM){ 
if($this->state === self::STATE_CONNECTING){
if($id === ConnectionRequest::$ID){
$dataPacket = new ConnectionRequest($packet->buffer);
$dataPacket->decode();
$pk = new ConnectionRequestAccepted;
$pk->address = $this->address;
$pk->sendPingTime = $dataPacket->sendPingTime;
$pk->sendPongTime = $this->sessionManager->getRakNetTimeMS();
$this->queueConnectedPacket($pk, PacketReliability::UNRELIABLE, 0, RakLib::PRIORITY_IMMEDIATE);
}elseif($id === NewIncomingConnection::$ID){
$dataPacket = new NewIncomingConnection($packet->buffer);
$dataPacket->decode();
if($dataPacket->address->port === $this->sessionManager->getPort() or !$this->sessionManager->portChecking){
$this->state = self::STATE_CONNECTED; 
$this->isTemporal = false;
$this->sessionManager->openSession($this);
$this->sendPing();
}
}
}elseif($id === DisconnectionNotification::$ID){
$this->disconnect('client disconnect');
}elseif($id === ConnectedPing::$ID){
$dataPacket = new ConnectedPing($packet->buffer);
$dataPacket->decode();
$pk = new ConnectedPong;
$pk->sendPingTime = $dataPacket->sendPingTime;
$pk->sendPongTime = $this->sessionManager->getRakNetTimeMS();
$this->queueConnectedPacket($pk, PacketReliability::UNRELIABLE, 0);
}elseif($id === ConnectedPong::$ID){
$dataPacket = new ConnectedPong($packet->buffer);
$dataPacket->decode();
$this->handlePong($dataPacket->sendPingTime, $dataPacket->sendPongTime);
}
}elseif($this->state === self::STATE_CONNECTED){
$this->sessionManager->streamEncapsulated($this, $packet);
}
}
private function handlePong(int $sendPingTime, int $sendPongTime) : void{
$this->lastPingMeasure = $this->sessionManager->getRakNetTimeMS() - $sendPingTime;
$this->sessionManager->streamPingMeasure($this, $this->lastPingMeasure);
}
public function handlePacket(Packet $packet) : void{
$this->isActive = true;
$this->lastUpdate = microtime(true);
if($packet instanceof Datagram){ 
$packet->decode();
if($packet->seqNumber < $this->windowStart or $packet->seqNumber > $this->windowEnd or isset($this->ACKQueue[$packet->seqNumber])){
$this->sessionManager->getLogger()->debug('Received duplicate or out-of-window packet from '. $this->address . " (sequence number $packet->seqNumber, window " . $this->windowStart . "-" . $this->windowEnd . ")");
return;
}
unset($this->NACKQueue[$packet->seqNumber]);
$this->ACKQueue[$packet->seqNumber] = $packet->seqNumber;
if($this->highestSeqNumberThisTick < $packet->seqNumber){
$this->highestSeqNumberThisTick = $packet->seqNumber;
}
if($packet->seqNumber === $this->windowStart){
for(; isset($this->ACKQueue[$this->windowStart]); ++$this->windowStart){
++$this->windowEnd;
}
}elseif($packet->seqNumber > $this->windowStart){
for($i = $this->windowStart; $i < $packet->seqNumber; ++$i){
if(!isset($this->ACKQueue[$i])){
$this->NACKQueue[$i] = $i;
}
}
}else{
assert(false, 'received packet before window start');
}
foreach($packet->packets as $pk){
assert($pk instanceof EncapsulatedPacket);
$this->handleEncapsulatedPacket($pk);
}
}else{
if($packet instanceof ACK){
$packet->decode();
foreach($packet->packets as $seq){
if(isset($this->recoveryQueue[$seq])){
foreach($this->recoveryQueue[$seq]->packets as $pk){
if($pk instanceof EncapsulatedPacket and $pk->needACK and $pk->messageIndex !== null){
unset($this->needACK[$pk->identifierACK][$pk->messageIndex]);
}
}
unset($this->recoveryQueue[$seq]);
}
}
}elseif($packet instanceof NACK){
$packet->decode();
foreach($packet->packets as $seq){
if(isset($this->recoveryQueue[$seq])){
$this->packetToSend[] = $this->recoveryQueue[$seq];
unset($this->recoveryQueue[$seq]);
}
}
}
}
}
public function flagForDisconnection() : void{
$this->state = self::STATE_DISCONNECTING;
$this->disconnectionTime = microtime(true);
}
public function close() : void{
if($this->state !== self::STATE_DISCONNECTED){
$this->state = self::STATE_DISCONNECTED;
$this->queueConnectedPacket(new DisconnectionNotification(), PacketReliability::RELIABLE_ORDERED, 0, RakLib::PRIORITY_IMMEDIATE);
$this->sessionManager->getLogger()->debug("Closed session for $this->address");
$this->sessionManager->removeSessionInternal($this);
}
}
}