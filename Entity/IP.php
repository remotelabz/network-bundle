<?php

namespace Remotelabz\NetworkBundle\Entity;

use InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class IP
{
    #[ORM\Column(type: "string", length: 255)]
    private $addr;


    // Long representation of addr (for database index purposes)
    #[ORM\Column(name: "_long", type: "bigint")]
    private $long;


    public function __construct(string $addr)
    {
        if (!filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException("Invalid IP address provided.");
        }
        $this->addr = $addr;
        $this->long = ip2long($addr);
    }

    public function getAddr(): ?string
    {
        return $this->addr;
    }

    public function setAddr(?string $addr): self
    {
        $this->addr = $addr;
        $this->long = ip2long($addr);

        return $this;
    }

    /** Returns a long representation of IP (equivalent to ip2long($ip)) */
    public function getLong(): int
    {
        return (int) $this->long;
    }

    public function __toString()
    {
        return $this->addr;
    }

    /**
     * Returns the address array-shaped.
     */
    public function getAddrArray(): array
    {
        return explode(".", $this->addr);
    }

    /**
     * Returns an array of address bytes.
     */
    public function getBinaryAddr(): array
    {
        $addr = explode(".", $this->addr);
        $bytes = array_map(function ($byte) {
            return sprintf("%08d", decbin($byte));
        }, $addr);
        return $bytes;
    }

    public function isNetmask(): bool
    {
        $bits = array_reduce($this->getBinaryAddr(), function ($stack, $item) {
            return $stack . $item;
        }, "");

        return preg_match("/^1+0+$/m", $bits) === 1;
    }
}
