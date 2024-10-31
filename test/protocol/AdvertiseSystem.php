<?php declare(strict_types=1);

namespace raklib\protocol;

class AdvertiseSystem extends Packet{

 public static int $ID = MessageIdentifiers::ID_ADVERTISE_SYSTEM;
 public string $serverName;

	protected function encodePayload() : void{
		$this->putString($this->serverName);
	}
	protected function decodePayload() : void{
		$this->serverName = $this->getString();
	}
}
