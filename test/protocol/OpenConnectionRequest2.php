<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\utils\InternetAddress;

class OpenConnectionRequest2 extends OfflineMessage{

 public static int $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_2;
 public int $clientID, $mtuSize;
 public InternetAddress $serverAddress;

protected function encodePayload() : void{
$this->writeMagic();
$this->putAddress($this->serverAddress);
$this->putShort($this->mtuSize);
$this->putLong($this->clientID);
}
protected function decodePayload() : void{
$this->readMagic();
$this->serverAddress = $this->getAddress();
$this->mtuSize = $this->getShort();
$this->clientID = $this->getLong();
}
}
