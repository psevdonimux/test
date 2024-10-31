<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class Datagram extends Packet{

	public const BITFLAG_VALID = 0x80;
	public const BITFLAG_ACK = 0x40;
	public const BITFLAG_NAK = 0x20; // hasBAndAS for ACKs
	public const BITFLAG_PACKET_PAIR = 0x10;
	public const BITFLAG_CONTINUOUS_SEND = 0x08;
	public const BITFLAG_NEEDS_B_AND_AS = 0x04;

	public int $headerFlags = 0;
	public array $packets = [];
	public ?int $seqNumber = null;

	protected function encodeHeader() : void{
		$this->putByte(self::BITFLAG_VALID | $this->headerFlags);
	}
	protected function encodePayload() : void{
		$this->putLTriad($this->seqNumber);
		foreach($this->packets as $packet){
			$this->put($packet instanceof EncapsulatedPacket ? $packet->toBinary() : $packet);
		}
	}
	public function length() : int{
		$length = 4;
		foreach($this->packets as $packet){
			$length += $packet instanceof EncapsulatedPacket ? $packet->getTotalLength() : strlen($packet);
		}
		return $length;
	}
	protected function decodeHeader() : void{
		$this->headerFlags = $this->getByte();
	}
	protected function decodePayload() : void{
		$this->seqNumber = $this->getLTriad();
		while(!$this->feof()){
			$offset = 0;
			$data = substr($this->buffer, $this->offset);
			$packet = EncapsulatedPacket::fromBinary($data, $offset);
			$this->offset += $offset;
			if($packet->buffer === ''){
				break;
			}
			$this->packets[] = $packet;
		}
	}
	public function clean() : Packet{
		$this->packets = [];
		$this->seqNumber = null;
		return parent::clean();
	}
}
