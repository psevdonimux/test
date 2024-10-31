<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class ConnectedPing extends Packet{

 public static int $ID = MessageIdentifiers::ID_CONNECTED_PING;
 public int $sendPingTime;

	protected function encodePayload() : void{
		$this->putLong($this->sendPingTime);
	}
	protected function decodePayload() : void{
		$this->sendPingTime = $this->getLong();
	}
}
