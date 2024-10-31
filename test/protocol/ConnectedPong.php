<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class ConnectedPong extends Packet{

 public static int $ID = MessageIdentifiers::ID_CONNECTED_PONG;
 public int $sendPingTime, $sendPongTime;

	protected function encodePayload() : void{
		$this->putLong($this->sendPingTime);
		$this->putLong($this->sendPongTime);
	}
	protected function decodePayload() : void{
		$this->sendPingTime = $this->getLong();
		$this->sendPongTime = $this->getLong();
	}
}
