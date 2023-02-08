<?php
declare(strict_types=1);

namespace MyPlot;

class Plot
{
    public string $levelName = "";
    public int $X = -0;
    public int $Z = -0;
    public string $name = "";
    public string $owner = "";
    /** @var string[] $helpers */
    public array $helpers = [];
    /** @var string[] $banned */
    public array $banned = [];
    public string $biome = "PLAINS";
    public bool $pvp = true;

    public function __construct(string $levelName, int $X, int $Z, string $name = "", string $owner = "", array $helpers = [], array $banned = [], string $biome = "PLAINS", ?bool $pvp = null)
    {
        $this->levelName = $levelName;
        $this->X = $X;
        $this->Z = $Z;
        $this->name = $name;
        $this->owner = $owner;
        $this->helpers = $helpers;
        $this->banned = $banned;
        $this->biome = strtoupper($biome);
        $settings = MyPlot::getInstance()->getLevelSettings($levelName);
        if (!isset($pvp)) {
            $this->pvp = !$settings->restrictPVP;
        } else {
            $this->pvp = $pvp;
        }
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function addHelper(string $username): bool
    {
        if (!$this->isHelper($username)) {
            $this->unBanPlayer($username);
            $this->helpers[] = $username;
            return true;
        }
        return false;
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function isHelper(string $username): bool
    {
        return in_array($username, $this->helpers, true);
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function unBanPlayer(string $username): bool
    {
        if (!$this->isBanned($username)) {
            return false;
        }
        $key = array_search($username, $this->banned, true);
        if ($key === false) {
            return false;
        }
        unset($this->banned[$key]);
        return true;
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function isBanned(string $username): bool
    {
        return in_array($username, $this->banned, true);
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function banPlayer(string $username): bool
    {
        if (!$this->isBanned($username)) {
            $this->removeHelper($username);
            $this->banned[] = $username;
            return true;
        }
        return false;
    }

    /**
     * @param string $username
     *
     * @return bool
     * @api
     *
     */
    public function removeHelper(string $username): bool
    {
        if (!$this->isHelper($username)) {
            return false;
        }
        $key = array_search($username, $this->helpers, true);
        if ($key === false) {
            return false;
        }
        unset($this->helpers[$key]);
        return true;
    }

    public function isSame(Plot $plot): bool
    {
        return $this->X === $plot->X and $this->Z === $plot->Z and $this->levelName === $plot->levelName;
    }

    public function __toString(): string
    {
        return "(" . $this->X . ";" . $this->Z . ")";
    }
}