<?php declare(strict_types=1);

namespace raklib;

abstract class RakLib{

 public const VERSION = '0.12.0';
 public const MIN_PHP_VERSION = '8.0.0';
 public const DEFAULT_PROTOCOL_VERSION = 6;
 public const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";
 public const PRIORITY_NORMAL = 0;
 public const PRIORITY_IMMEDIATE = 1;
 public const FLAG_NEED_ACK = 0b00001000;
 public const PACKET_ENCAPSULATED = 0x01;
 public const PACKET_OPEN_SESSION = 0x02;
 public const PACKET_CLOSE_SESSION = 0x03;
 public const PACKET_INVALID_SESSION = 0x04;
 public const PACKET_SEND_QUEUE = 0x05;
 public const PACKET_ACK_NOTIFICATION = 0x06;
 public const PACKET_SET_OPTION = 0x07;
 public const PACKET_RAW = 0x08;
 public const PACKET_BLOCK_ADDRESS = 0x09;
 public const PACKET_UNBLOCK_ADDRESS = 0x10;
 public const PACKET_REPORT_PING = 0x11;
 public const PACKET_SHUTDOWN = 0x7e;
 public const PACKET_EMERGENCY_SHUTDOWN = 0x7f;
 public static int $SYSTEM_ADDRESS_COUNT = 20;
}
