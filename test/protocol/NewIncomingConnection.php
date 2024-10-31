<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\RakLib;
use raklib\utils\InternetAddress;

class NewIncomingConnection extends Packet{

 public static int $ID = MessageIdentifiers::ID_NEW_INCOMING_CONNECTION;
 public InternetAddress $address;
 public array $systemAddresses = [];
 public int $sendPingTime, $sendPongTime;

protected function encodePayload() : void{
$this->putAddress($this->address);
foreach($this->systemAddresses as $address){
$this->putAddress($address);
}
$this->putLong($this->sendPingTime);
$this->putLong($this->sendPongTime);
}
protected function decodePayload() : void{
$this->address = $this->getAddress();
$stopOffset = strlen($this->buffer) - 16; 
$dummy = new InternetAddress('0.0.0.0', 0, 4);
for($i = 0; $i < RakLib::$SYSTEM_ADDRESS_COUNT; ++$i){
if($this->offset >= $stopOffset){
$this->systemAddresses[$i] = clone $dummy;
}else{
$this->systemAddresses[$i] = $this->getAddress();
}
}
$this->sendPingTime = $this->getLong();
$this->sendPongTime = $this->getLong();
}
}
