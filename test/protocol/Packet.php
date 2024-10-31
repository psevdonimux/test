<?php declare(strict_types=1);

namespace raklib\protocol;

use pocketmine\utils\{BinaryDataException, BinaryStream, Binary};
use raklib\utils\InternetAddress;
#ifndef COMPILE
#endif

#include <rules/RakLibPacket.h>

abstract class Packet extends BinaryStream{

public static int $ID = -1;
public ?float $sendTime;

public function getString() : string{
return $this->get($this->getShort());
}
protected function getAddress() : InternetAddress{
$version = $this->getByte();
if($version === 4){
$addr = ((~$this->getByte()) & 0xff) . "." . ((~$this->getByte()) & 0xff) . "." . ((~$this->getByte()) & 0xff) . "." . ((~$this->getByte()) & 0xff);
$port = $this->getShort();
return new InternetAddress($addr, $port, $version);
}elseif($version === 6){
Binary::readLShort($this->get(2)); //Family, AF_INET6
$port = $this->getShort();
$this->getInt(); //flow info
$addr = inet_ntop($this->get(16));
if(!$addr){
throw new BinaryDataException("Failed to parse IPv6 address");
}
$this->getInt(); //scope ID
return new InternetAddress($addr, $port, $version);
}else{
throw new \UnexpectedValueException("Unknown IP address version $version");
}
}
public function putString(mixed $v) : void{
$this->putShort(strlen($v));
$this->put($v);
}
protected function putAddress(InternetAddress $address) : void{
$this->putByte($address->version);
if($address->version === 4){
$parts = explode('.', $address->ip);
assert(count($parts) === 4, "Wrong number of parts in IPv4 IP, expected 4, got " . count($parts));
foreach($parts as $b){
$this->putByte((~((int) $b)) & 0xff);
}
$this->putShort($address->port);
}elseif($address->version === 6){
$this->put(Binary::writeLShort(AF_INET6));
$this->putShort($address->port);
$this->putInt(0);
$rawIp = inet_pton($address->ip);
if($rawIp === false){
throw new \InvalidArgumentException("Invalid IPv6 address could not be encoded");
}
$this->put($rawIp);
$this->putInt(0);
}else{
throw new \InvalidArgumentException("IP version $address->version is not supported");
}
}
public function encode() : void{
$this->reset();
$this->encodeHeader();
$this->encodePayload();
}
protected function encodeHeader() : void{
$this->putByte(static::$ID);
}
abstract protected function encodePayload() : void;
public function decode() : void{
$this->offset = 0;
$this->decodeHeader();
$this->decodePayload();
}
protected function decodeHeader() : void{
$this->getByte(); //PID
}
abstract protected function decodePayload() : void;
public function clean() : Packet{
$this->buffer = '';
$this->offset = 0;
$this->sendTime = null;
return $this;
}
}
