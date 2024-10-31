<?php declare(strict_types=1);

namespace raklib\server;

use raklib\protocol\EncapsulatedPacket;

interface ServerInstance{

 public function openSession(string $identifier, string $address, int $port, int $clientID) : void;
 public function closeSession(string $identifier, string $reason) : void;
 public function handleEncapsulated(string $identifier, EncapsulatedPacket $packet, int $flags) : void;
 public function handleRaw(string $address, int $port, string $payload) : void;
 public function notifyACK(string $identifier, int $identifierACK) : void;
 public function handleOption(string $option, string $value) : void;
 public function updatePing(string $identifier, int $pingMS) : void;
}
