<?php

namespace Duplicator\Views;

class UserUIOptions
{
    const USER_UI_OPTION_META_KEY = 'duplicator_user_ui_option';

    const VAL_PACKAGES_PER_PAGE   = 'num_packages_list';
    const VAL_CREATED_DATE_FORMAT = 'created_date_format';
    const VAL_SHOW_COL_NOTE       = 'show_note_column';
    const VAL_SHOW_COL_SIZE       = 'show_size_column';
    const VAL_SHOW_COL_CREATED    = 'show_created_column';
    const VAL_SHOW_COL_AGE        = 'show_age_column';

    /** @var ?self */
    private static $instance;

    /** @var int */
    private $userId = 0;
    /** @var array<string,scalar> */
    private $options = [
        self::VAL_PACKAGES_PER_PAGE   => 10,
        self::VAL_CREATED_DATE_FORMAT => 1,
        self::VAL_SHOW_COL_NOTE       => false,
        self::VAL_SHOW_COL_SIZE       => true,
        self::VAL_SHOW_COL_CREATED    => true,
        self::VAL_SHOW_COL_AGE        => false,
    ];

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * CLass constructor
     */
    protected function __construct()
    {
        $this->userId = get_current_user_id();
        $this->load();
    }

    /**
     * Get the value of an option
     *
     * @param string $option the option name
     *
     * @return scalar
     */
    public function get($option)
    {
        if (!isset($this->options[$option])) {
            return false;
        }

        return $this->options[$option];
    }

    /**
     * Set the value of an option
     *
     * @param string $option the option name
     * @param scalar $value  the option value
     *
     * @return void
     */
    public function set($option, $value): void
    {
        if (!isset($this->options[$option])) {
            // don't set unknown options
            return;
        }
        $this->options[$option] = $value;
    }

    /**
     * Load the option from meta user table
     *
     * @return void
     */
    protected function load()
    {
        if ($this->userId == 0) {
            return;
        }

        $options = get_user_meta($this->userId, self::USER_UI_OPTION_META_KEY, true);
        if (is_array($options)) {
            foreach (array_keys($this->options) as $option) {
                $this->options[$option] = $options[$option];
            }
        }

        $this->loadOldValues();
    }

    /**
     * Load old values from meta user table
     *
     * @return void
     */
    protected function loadOldValues()
    {
        $save = false;
        //Inheriting the value of the old created format option to the screen option
        $createdFormat = get_user_meta($this->userId, 'duplicator_pro_created_format', true);
        if (is_numeric($createdFormat)) {
            $save = true;
            $this->options[self::VAL_CREATED_DATE_FORMAT] = $createdFormat;
            delete_user_meta($this->userId, 'duplicator_pro_created_format');
        }

        $perPage = get_user_meta($this->userId, 'duplicator_pro_opts_per_page', true);
        if (is_numeric($perPage)) {
            $save = true;
            $this->options[self::VAL_PACKAGES_PER_PAGE] = $perPage;
            delete_user_meta($this->userId, 'duplicator_pro_opts_per_page');
        }

        if ($save) {
            $this->save();
        }
    }

    /**
     * Save the option to meta user table
     *
     * @return void
     */
    public function save(): void
    {
        if ($this->userId == 0) {
            return;
        }
        update_user_meta($this->userId, self::USER_UI_OPTION_META_KEY, $this->options);
    }
}
