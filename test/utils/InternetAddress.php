<?php declare(strict_types=1);

namespace raklib\utils;

class InternetAddress{

 public string $ip;
 public int $port, $version;

 public function __construct(string $address, int $port, int $version){
  $this->ip = $address;
  if($port < 0 or $port > 65535){
   throw new \InvalidArgumentException('Invalid port range');
  }
  $this->port = $port;
  $this->version = $version;
 }
 public function getIp() : string{
  return $this->ip;
 }
 public function getPort() : int{
  return $this->port;
 }
 public function getVersion() : int{
  return $this->version;
 }
 public function __toString(){
  return $this->ip . ' '. $this->port;
 }
 public function toString() : string{
  return $this->__toString();
 }
 public function equals(InternetAddress $address) : bool{
  return $this->ip === $address->ip and $this->port === $address->port and $this->version === $address->version;
 }
}