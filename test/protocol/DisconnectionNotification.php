<?php declare(strict_types=1);

namespace raklib\protocol;

class DisconnectionNotification extends Packet{

	public static int $ID = MessageIdentifiers::ID_DISCONNECTION_NOTIFICATION;

	protected function encodePayload() : void{}
	protected function decodePayload() : void{}
}
