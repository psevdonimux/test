<?php declare(strict_types=1);

namespace raklib\protocol;

use raklib\RakLib;

abstract class OfflineMessage extends Packet{

 protected string $magic;

protected function readMagic() : void{
$this->magic = $this->get(16);
}
protected function writeMagic() : void{
$this->put(RakLib::MAGIC);
}
public function isValid() : bool{
return $this->magic === RakLib::MAGIC;
}
}
