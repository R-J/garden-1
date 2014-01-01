<?php

namespace Vanilla;

/**
 * Contains methods for binding and firing to events.
 *
 * Addons can create callbacks that bind to events which are calle througout the code to allow
 * extension of the application and framework.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license LGPL-2.1
 * @package Vanilla
 * @since 1.0
 */
class Event {
    /// Constants ///

    const PRIORITY_LOW = 1000;
    const PRIORITY_MEDIUM = 100;
    const PRIORITY_HIGH = 10;

    /// Properties ///

    /**
     * All of the event handlers that have been registered.
     * @var array An array of event handlers.
     */
    protected static $handlers = array();

    /**
     * All of the event handlers that still need to be sorted by priority.
     * @var type
     */
    protected static $toSort = array();

    /// Methods ///

    /**
     * Call a callback with an array of parameters, checking for events to be called with the callback.
     *
     * This method is similar to {@link call_user_func_array()} but it will fire events that can happen
     * before and/or after the callback and can call an event instead of the callback itself to override it.
     *
     * In order to use events with this method they must be bound with a particular naming convention.
     *
     * **Modify a function**
     *
     * - functionname_before
     * - functionname
     * - functionname_after
     *
     * **Modify a method call**
     *
     * - classname_methodname_before
     * - classname_methodname
     * - classname_methodname_after
     *
     * @param callable $callback The {@link callable} to be called.
     * @param array $args The arguments to be passed to the callback, as an indexed array.
     * @return mixed Returns the return value of the callback.
     */
    public static function callUserFuncArray($callback, $args) {
        // Figure out the event name from the callback.
        if (is_string($callback)) {
            $event_name = $callback;
        } elseif (is_array($callback)) {
            if (is_string($callback[0])) {
                $event_name = $callback[0].'_'.$callback[1];
            } else {
                $event_name = get_class($callback[0]).'_'.$callback[1];
            }
        } else {
            return call_user_func_array($callback, $args);
        }

        // The events could have different arguments because the event handler can take the object as the first parameter.
        $event_args = $args;
        // If the callback is an object then it gets passed as the first argument.
        if (is_array($callback) && is_object($callback[0])) {
            array_unshift($event_args, $callback[0]);
        }

        // Fire before events.
        self::fire($event_name.'_before', $event_args);

        // Call the function.
        if (static::hasHandler($event_name)) {
            // The callback was overridden so fire it.
            $result = static::fire($event_name, $event_args);
        } else {
            // The callback was not overridden so just call the passed callback.
            $result = call_user_func_array($callback, $args);
        }

        // Fire after events.
        self::fire($event_name.'_after', $event_args);

        return $result;
    }

    /**
     * Bind an event handler to an event.
     *
     * @param string $event The naame of the event to bind to.
     * @param callback $callback The callback of the event.
     * @param int $priority The priority of the event.
     */
    public static function bind($event, $callback, $priority = self::PRIORITY_MEDIUM) {
        $event = strtolower($event);
        self::$handlers[$event][$priority][] = $callback;
        self::$toSort[$event] = true;
    }

    /**
     * Bind a class' declared event handlers.
     *
     * Plugin classes declare event handlers in the following way:
     *
     * ```
     * // Bind to a normal event.
     * public function eventname_handler($arg1, $arg2, ...) { ... }
     *
     * // Add/override a method called with Event::callUserFuncArray().
     * public function ClassName_methodName($sender, $arg1, $arg2) { ... }
     * public function ClassName_methodName_create($sender, $arg1, $arg2) { ... } // deprecated
     *
     * // Call the handler before or after a method called with Event::callUserFuncArray().
     * public function ClassName_methodName_before($sender, $arg1, $arg2) { ... }
     * public function ClassName_methodName_after($sender, $arg1, $arg2) { ... }
     * ```
     *
     * @param mixed $class The class name or an object instance.
     * @param int $priority The priority of the event.
     */
    public static function bindClass($class, $priority = self::PRIORITY_MEDIUM) {
        $method_names = get_class_methods($class);

        // Grab a instance of the class so there is something to bind to.
        if (is_string($class)) {
            if (method_exists($class, 'instance')) {
                // TODO: Make the instance lazy load.
                $instance = call_user_func(array($class, 'instance'));
            } else {
                throw new \InvalidArgumentException('Event::bindClass(): The class for argument #1 must have an instance() method or be passed as an object.', 500);
            }
        } else {
            $instance = $class;
        }

        foreach ($method_names as $method_name) {
            if (strpos($method_name, '_') === false)
                continue;

            $parts = explode('_', strtolower($method_name));
            $event_name = '';
            switch (end($parts)) {
                case 'handler':
                case 'create':

                    break;
                case 'before':
                case 'after':

                    break;
            }
            // Bind the event if we have one.
            if ($event_name) {
                static::bind($event_name, array($instance, $method_name), $priority);
            }
        }
    }

    /**
     * Fire an event.
     *
     * @param string $event The name of the event.
     * @return int How many times the event was handled.
     */
    public static function fire($event) {
        $handlers = self::getHandlers($event);
        if ($handlers === false)
            return 0;

        // Grab the handlers and call them.
        $args = array_slice(func_get_args(), 1);
        $result = null;
        foreach ($handlers as $callbacks) {
            foreach ($callbacks as $callback) {
                $result = call_user_func_array($callback, $args);
            }
        }
        return $result;
    }

    /**
     * Chain several event handlers together.
     * This method will fire the first handler and pass its result as the first argument
     * to the next event handler and so on. A chained event handler can have more than one parameter,
     * but must have at least one parameter.
     *
     * @param string $event The name of the event to fire.
     * @param mixed $value The value to pass into the filter.
     * @return mixed The result of the chained event or `$value` if there were no handlers.
     */
    public static function fireFilter($event, $value) {
        $handlers = self::getHandlers($event);
        if ($handlers === false)
            return $value;

        $args = array_slice(func_get_args(), 1);
        foreach ($handlers as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, $args);
                $args[0] = $value;
            }
        }
        return $value;
    }

    /**
     * Fire an event meant to override another function or method.
     * The event handler should return true if it wants to say it is overridding the method.
     *
     * @param string $event The name of the event to fire.
     * @return bool Whether or not the event has been overridden.
     */
//   public static function fireOverride($event) {
//      $handlers = self::getHandlers($event);
//      if ($handlers === false)
//         return $value;
//
//      $args = array_slice(func_get_args(), 1);
//      foreach ($handlers as $callbacks) {
//         foreach ($callbacks as $callback) {
//            $overridden = call_user_func_array($callback, $args);
//            if ($overridden)
//               return true;
//         }
//      }
//      return false;
//   }

    /**
     * Checks if a function exists or there is a replacement event for it.
     *
     * @param string $function_name The function name.
     * @param bool $only_events Whether or not to only check events.
     * @return boolean Returns `true` if the function given by `function_name` has been defined, `false` otherwise.
     * @see http://ca1.php.net/manual/en/function.function-exists.php
     */
    public static function functionExists($function_name, $only_events = false) {
        if (!$only_events && function_exists($function_name)) {
            return true;
        } else {
            return static::hasHandler($function_name);
        }
    }

    /**
     * Get all of the handlers bound to an event.
     *
     * @param string $name
     * @return boolean
     */
    public static function getHandlers($name) {
        $name = strtolower($name);

        if (!isset(self::$handlers[$name]))
            return false;

        // See if the handlers need to be sorted.
        if (isset(self::$toSort[$name])) {
            ksort(self::$handlers[$name]);
            unset(self::$toSort[$name]);
        }

        return self::$handlers[$name];
    }

    /**
     * Checks if an event has a handler.
     *
     * @param string $event The name of the event.
     * @return bool Returns `true` if the event has at least one handler, `false` otherwise.
     */
    public static function hasHandler($event) {
        $event = strtolower($event);
        return array_key_exists($event, self::$handlers) && !empty(self::$handlers[$event]);
    }

    /**
     * Checks if a class method exists or there is a replacement event for it.
     *
     * @param mixed $object An object instance or a class name.
     * @param string $method_name The method name.
     * @param bool $only_events Whether or not to only check events.
     * @return boolean Returns `true` if the method given by method_name has been defined for the given object, `false` otherwise.
     * @see http://ca1.php.net/manual/en/function.method-exists.php
     */
    public static function methodExists($object, $method_name, $only_events = false) {
        if (!$only_events && method_exists($object, $method_name)) {
            return true;
        } else {
            // Check to see if there is an event bound to the method.
            if (is_string($object)) {
                $event_name = $object.'_'.$method_name;
            } else {
                $event_name = get_class($object).'_'.$method_name;
            }
            return static::hasHandler($event_name);
        }
    }

    /**
     * Clear all of the event handlers.
     *
     * This method resets the event object to its original state.
     */
    public static function reset() {
        self::$handlers = array();
        self::$toSort = array();
    }
}
