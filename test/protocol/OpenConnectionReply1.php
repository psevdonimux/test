<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class OpenConnectionReply1 extends OfflineMessage{

 public static int $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_1;
 public int $serverID, $mtuSize;
 public bool $serverSecurity = false;

protected function encodePayload() : void{
$this->writeMagic();
$this->putLong($this->serverID);
$this->putByte($this->serverSecurity ? 1 : 0);
$this->putShort($this->mtuSize);
}
protected function decodePayload() : void{
$this->readMagic();
$this->serverID = $this->getLong();
$this->serverSecurity = $this->getByte() !== 0;
$this->mtuSize = $this->getShort();
}
}
