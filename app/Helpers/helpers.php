<?php

if (!function_exists('broadcast_if')) {
    /**
     * Broadcast the given event if the given condition is true.
     *
     * @param bool $condition
     * @param mixed $event
     * @return void
     */
    function broadcast_if($condition, $event)
    {
        if ($condition) {
            broadcast($event);
        }
    }
}
