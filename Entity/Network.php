<?php

namespace Remotelabz\NetworkBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Remotelabz\NetworkBundle\Exception\BadNetmaskException;

#[ORM\Entity(repositoryClass: "App\Repository\NetworkRepository")]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Embedded(class: IP::class)]
    #[Serializer\Groups(["lab", "start_lab", "stop_lab"])]
    private $ip;

    #[ORM\Embedded(class: IP::class)]
    #[Serializer\Groups(["lab", "start_lab", "stop_lab"])]
    private $netmask;

    public function __construct(string $ip, string $netmask)
    {
        $this->ip = new IP($ip);
        $this->netmask = new IP($netmask);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): ?IP
    {
        return $this->ip;
    }

    public function setIp(IP $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getNetmask(): ?IP
    {
        return $this->netmask;
    }

    public function setNetmask(IP $netmask): self
    {
        $this->netmask = $netmask;

        return $this;
    }

    /**
     * Returns a count of true bits in a network mask, commonly used in CIDR notation of IPv4.
     *
     * @return int Size of the netmask
     */
    public function getCidrNetmask(): int
    {
        $netmask = array_reduce($this->getNetmask()->getAddrArray(), function ($payload, $byte) {
            return $payload . decbin($byte);
        }, "");
        return substr_count($netmask, "1");
    }

    public function getNextNetwork(): Network
    {
        return new Network(long2ip(ip2long($this->ip) + $this->count(false)), $this->netmask);
    }

    public function getFirstAddress(): IP
    {
        return new IP(long2ip(ip2long($this->ip) + 1));
    }

    public function getLastAddress(): IP
    {
        return new IP(long2ip(ip2long($this->ip) + (pow(2, 32 - $this->getCidrNetmask()) - 2)));
    }

    /**
     * Get all host IP of this network.
     *
     * @param IP[]|null $excluded If specified, exclude specified IP address in array from IP range.
     *
     * @return IP[]
     */
    public function getAllIp(?array $excluded = null)
    {
        $range = [];
        $first = ip2long($this->getFirstAddress());

        for ($i = 0; $i < $this->count(); $i++) {
            $ip = long2ip($first + $i);

            if (!$excluded || is_array($excluded) && count(array_filter($excluded, function ($el) use ($ip) {
                return $el->getAddr() == $ip;
            })) == 0) {
                $range[] = new IP($ip);
            }
        }

        return $range;
    }

    /**
     * Count the maximum number of hosts within this network.
     *
     * @param bool $hostsOnly If result must be returned minus 2 (excluding broadcast and network addresses). Default to true.
     *
     * @return int
     */
    public function count(bool $hostsOnly = true): int
    {
        $count = (1 << (32 - $this->getCidrNetmask()));

        return $hostsOnly ? $count - 2 : $count;
    }

    /**
     * Get all possible subnetworks of this network based to the provided netmask.
     *
     * @param IP $moveTo The netmask to apply to split this network.
     *
     * @return Network[]
     */
    public function split(IP $moveTo)
    {
        if (!$moveTo->isNetmask()) {
            throw new BadNetmaskException();
        }

        $moveToNetwork = new Network($this->getIp(), $moveTo);
        $diff = $moveToNetwork->getCidrNetmask() - $this->getCidrNetmask();

        if ($diff <= 0) {
            throw new BadNetmaskException();
        }

        $splitCount = pow(2, $diff);
        $split = [];
        $next = $moveToNetwork;

        for ($i = 0; $i < $splitCount; $i++) {
            $split[] = new Network($next->getIp(), $next->getNetmask());
            $next = $next->getNextNetwork();
        }

        return $split;
    }

    public function __toString()
    {
        return $this->ip . "/" . $this->getCidrNetmask();
    }
}
