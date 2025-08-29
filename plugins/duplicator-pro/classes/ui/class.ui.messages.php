<?php

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Used to generate a thick box inline dialog such as an alert or confirm pop-up
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    Duplicator
 * @subpackage classes/ui
 * @copyright  (c) 2017, Snapcreek LLC
 */

class DUP_PRO_UI_Messages
{
    const UNIQUE_ID_PREFIX = 'dup_ui_msg_';
    const NOTICE           = 'updated';
    const WARNING          = 'update-nag';
    const ERROR            = 'error';

    /** @var int */
    private static $unique_id = 0;
    private string $id;
    /** @var string */
    public $type = self::NOTICE;
    /** @var string */
    public $content = '';
    /** @var bool */
    public $hide_on_init = true;
    /** @var bool */
    public $is_dismissible = false;
    /** @var int delay in milliseconds */
    public $auto_hide_delay = 0;
    /** @var string */
    public $callback_on_show = '';
    /** @var string */
    public $callback_on_hide = '';

    /**
     * Class constructor
     *
     * @param string $content Content of the message
     * @param string $type    Type of the message (NOTICE, WARNING, ERROR)
     */
    public function __construct($content = '', $type = self::NOTICE)
    {
        self::$unique_id++;
        $this->id = self::UNIQUE_ID_PREFIX . self::$unique_id;

        $this->content = (string) $content;
        $this->type    = $type;
    }

    /**
     * Get the classes for the notice
     *
     * @param string[] $classes Additional classes
     *
     * @return string
     */
    protected function get_notice_classes($classes = []): string
    {
        if (is_string($classes)) {
            $classes = explode(' ', $classes);
        } elseif (is_array($classes)) {
        } else {
            $classes = [];
        }

        if ($this->is_dismissible) {
            $classes[] = 'is-dismissible';
        }

        $result = array_merge(['notice', $this->type], $classes);
        return trim(implode(' ', $result));
    }

    /**
     * Initialize the message
     *
     * @return void
     */
    public function initMessage(): void
    {
        $classes = [];
        if ($this->hide_on_init) {
            $classes[] = 'no-display';
        }
        ?>
        <div id="<?php echo esc_attr($this->id); ?>" class="<?php echo esc_attr($this->get_notice_classes($classes)); ?>">
            <p class="msg-content">
            <?php echo $this->content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </p>
        </div>
        <?php
    }

    /**
     * Update the message content
     *
     * @param string $jsVarName Name of the variable containing the new content
     *
     * @return void
     */
    public function updateMessage($jsVarName): void
    {
        echo 'jQuery("#' . esc_js($this->id) . ' > .msg-content").html(' . esc_js($jsVarName) . ');';
    }

    /**
     * Show the message
     *
     * @return void
     */
    public function showMessage(): void
    {
        echo 'jQuery("body, html").animate({ scrollTop: 0 }, 500 );';
        echo 'jQuery("#' . esc_js($this->id) . '").fadeIn( "slow", function() { jQuery(this).removeClass("no_display");});';

        if ($this->auto_hide_delay > 0) {
            echo 'setTimeout(function () { jQuery("#' . esc_js($this->id) . '").fadeOut( "slow", function() { jQuery(this).addClass("no_display");}); }, '
                . (int) $this->auto_hide_delay . ');';
        }
    }
}
