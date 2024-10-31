<?php declare(strict_types=1);

namespace raklib\server;

use raklib\protocol\{IncompatibleProtocolVersion, OfflineMessage, OpenConnectionReply1, OpenConnectionReply2, OpenConnectionRequest1, OpenConnectionRequest2, UnconnectedPing, UnconnectedPong};
use raklib\utils\InternetAddress;

class OfflineMessageHandler{

 private SessionManager $sessionManager;

 public function __construct(SessionManager $manager){
  $this->sessionManager = $manager;
 }
 public function handle(OfflineMessage $packet, InternetAddress $address) : bool{
  switch($packet::$ID){
  case UnconnectedPing::$ID:
  $pk = new UnconnectedPong();
  $pk->serverID = $this->sessionManager->getID();
  $pk->pingID = $packet->pingID;
  $pk->serverName = $this->sessionManager->getName();
  $this->sessionManager->sendPacket($pk, $address);
  return true;
  case OpenConnectionRequest1::$ID:
  $serverProtocol = $this->sessionManager->getProtocolVersion();
  if($packet->protocol !== $serverProtocol){
   $pk = new IncompatibleProtocolVersion();
   $pk->protocolVersion = $serverProtocol;
   $pk->serverId = $this->sessionManager->getID();
   $this->sessionManager->sendPacket($pk, $address);
   $this->sessionManager->getLogger()->notice("Refused connection from $address due to incompatible RakNet protocol version (expected $serverProtocol, got $packet->protocol)");
  }
  else{
   $pk = new OpenConnectionReply1();
   $pk->mtuSize = $packet->mtuSize + 28; 
   $pk->serverID = $this->sessionManager->getID();
   $this->sessionManager->sendPacket($pk, $address);
  }
  return true;
  case OpenConnectionRequest2::$ID:
   if($packet->serverAddress->port === $this->sessionManager->getPort() or !$this->sessionManager->portChecking){
  if($packet->mtuSize < Session::MIN_MTU_SIZE){
   $this->sessionManager->getLogger()->debug("Not creating session for $address due to bad MTU size $packet->mtuSize");
   return true;
  }
  $mtuSize = min($packet->mtuSize, $this->sessionManager->getMaxMtuSize()); //Max size, do not allow creating large buffers to fill server memory
  $pk = new OpenConnectionReply2();
  $pk->mtuSize = $mtuSize;
  $pk->serverID = $this->sessionManager->getID();
  $pk->clientAddress = $address;
  $this->sessionManager->sendPacket($pk, $address);
  $this->sessionManager->createSession($address, $packet->clientID, $mtuSize);
   }
   else{
  $this->sessionManager->getLogger()->debug("Not creating session for $address due to mismatched port, expected " . $this->sessionManager->getPort() . ", got " . $packet->serverAddress->port);
   }
  return true;
  }
  return false;
 }
}