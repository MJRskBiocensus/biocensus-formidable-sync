<?php

defined('ABSPATH') || defined('DUPXABSPATH') || exit;
/**
 * Defines the scope from which a filter item was created/retrieved from
 *
 * @package DupicatorPro\classes
 */
class DUP_PRO_Archive_Filter_Scope_Base
{
    /** @var string[] All internal storage items that we decide to filter */
    public $Core = [];
    //TODO: Enable with Settings UI

    /** @var string[] Global filter items added from settings */
    public $Global = [];
    /** @var string[] Items when creating a Backup or template */
    public $Instance = [];
    /** @var string[] Items that are not readable */
    public $Unreadable = [];
    /** @var string[] Unkonwn item path */
    public $Unknown = [];
    /** @var int Number of unreadable items */
    private $unreadableCount = 0;

    /**
     * Filter props on json encode
     *
     * @return string[]
     */
    public function __sleep()
    {
        $props = array_keys(get_object_vars($this));
        return array_diff($props, ['unreadableCount']);
    }

    /**
     * @param string $item A path to an unreadable item
     *
     * @return void
     */
    public function addUnreadableItem($item): void
    {
        $this->unreadableCount++;
        if ($this->unreadableCount <= DUPLICATOR_PRO_SCAN_MAX_UNREADABLE_COUNT) {
            $this->Unreadable[] = $item;
        }
    }

    /**
     * @return int returns number of unreadable items
     */
    public function getUnreadableCount()
    {
        return $this->unreadableCount;
    }
}

/**
 * Defines the scope from which a filter item was created/retrieved from
 *
 * @package DupicatorPro\classes
 */
class DUP_PRO_Archive_Filter_Scope_Directory extends DUP_PRO_Archive_Filter_Scope_Base
{
    /**
     * @var string[] Directories containing other WordPress installs
     */
    public $AddonSites = [];
    /**
     * @var array<array<string,mixed>> Items that are too large
     */
    public $Size = [];
}

/**
 * Defines the scope from which a filter item was created/retrieved from
 *
 * @package DupicatorPro\classes
 */
class DUP_PRO_Archive_Filter_Scope_File extends DUP_PRO_Archive_Filter_Scope_Base
{
    /**
     * @var array<array<string,mixed>> Items that are too large
     */
    public $Size = [];
}

/**
 * Defines the filtered items that are pulled from there various scopes
 *
 * @package DupicatorPro\classes
 */
class DUP_PRO_Archive_Filter_Info
{
    /** @var ?DUP_PRO_Archive_Filter_Scope_Directory Contains all folder filter info */
    public $Dirs;
    /** @var ?DUP_PRO_Archive_Filter_Scope_File Contains all folder filter info */
    public $Files;
    /** @var ?DUP_PRO_Archive_Filter_Scope_Base Contains all folder filter info */
    public $Exts;
    /** @var null|array<string,mixed>|DUP_PRO_Tree_files tree size structure for client jstree */
    public $TreeSize;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->reset(true);
    }

    /**
     * Clone
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->Dirs)) {
            $this->Dirs = clone $this->Dirs;
        }
        if (is_object($this->Files)) {
            $this->Files = clone $this->Files;
        }
        if (is_object($this->Exts)) {
            $this->Exts = clone $this->Exts;
        }
        if (is_object($this->TreeSize)) {
            $this->TreeSize = clone $this->TreeSize;
        }
    }

    /**
     * reset and clean all object
     *
     * @param bool $initTreeObjs if true then init tree size object
     *
     * @return void
     */
    public function reset($initTreeObjs = false): void
    {
        $exclude = [
            "Unreadable",
            "Instance",
        ];
        if (is_null($this->Dirs)) {
            $this->Dirs = new DUP_PRO_Archive_Filter_Scope_Directory();
        } else {
            $this->resetMember($this->Dirs, $exclude);
        }

        if (is_null($this->Files)) {
            $this->Files = new DUP_PRO_Archive_Filter_Scope_File();
        } else {
            $this->resetMember($this->Files, $exclude);
        }

        $this->Exts     = new DUP_PRO_Archive_Filter_Scope_Base();
        $this->TreeSize = $initTreeObjs ? new DUP_PRO_Tree_files(ABSPATH, false) : null;
    }

    /**
     * Resets all properties of $member to their default values except the ones in $exclude
     *
     * @param object   $member  Object to reset
     * @param string[] $exclude Properties to exclude from resetting
     *
     * @return void
     */
    private function resetMember($member, array $exclude = []): void
    {
        $refClass = new ReflectionClass($member);
        $defaults = $refClass->getDefaultProperties();
        foreach ($member as $key => $value) {
            if (!in_array($key, $exclude)) {
                $member->$key = $defaults[$key] ?? null;
            }
        }
    }
}
