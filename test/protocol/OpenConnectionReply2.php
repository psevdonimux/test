<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\utils\InternetAddress;

class OpenConnectionReply2 extends OfflineMessage{

 public static int $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_2;
 public int $serverID, $mtuSize;
 public InternetAddress $clientAddress;
 public bool $serverSecurity = false;

protected function encodePayload() : void{
$this->writeMagic();
$this->putLong($this->serverID);
$this->putAddress($this->clientAddress);
$this->putShort($this->mtuSize);
$this->putByte($this->serverSecurity ? 1 : 0);
}
protected function decodePayload() : void{
$this->readMagic();
$this->serverID = $this->getLong();
$this->clientAddress = $this->getAddress();
$this->mtuSize = $this->getShort();
$this->serverSecurity = $this->getByte() !== 0;
}
}
