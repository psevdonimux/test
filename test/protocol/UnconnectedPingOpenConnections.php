<?php declare(strict_types=1);

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class UnconnectedPingOpenConnections extends UnconnectedPing{
 public static int $ID = MessageIdentifiers::ID_UNCONNECTED_PING_OPEN_CONNECTIONS;
}
