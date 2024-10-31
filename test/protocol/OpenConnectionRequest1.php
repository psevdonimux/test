<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\RakLib;

class OpenConnectionRequest1 extends OfflineMessage{
	
 public static int $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_1;
 public int $protocol = RakLib::DEFAULT_PROTOCOL_VERSION, $mtuSize;

protected function encodePayload() : void{
$this->writeMagic();
$this->putByte($this->protocol);
$this->buffer = str_pad($this->buffer, $this->mtuSize, "\x00");
}
protected function decodePayload() : void{
$this->readMagic();
$this->protocol = $this->getByte();
$this->mtuSize = strlen($this->buffer);
}
}
