<?php

namespace LazySession;

use Symfony\Component\HttpFoundation\Session;

/**
 * Lazy Session.
 */
class LazySession extends Session
{
    
    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     */
    public function has($name)
    {
      if (false === $this->started) {
          $this->start();
      }
      
      return parent::has($name);
    }

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
      if (false === $this->started) {
          $this->start();
      }
      
      return parent::get($name, $default = null);
    }

    /**
     * Returns attributes.
     *
     * @return array Attributes
     */
    public function all()
    {
      if (false === $this->started) {
          $this->start();
      }
      
      return parent::all();
    }

    /**
     * Invalidates the current session.
     */
    public function invalidate()
    {
      if (false === $this->started) {
          $this->start();
      }
      
      parent::invalidate();
    }

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     */
    public function migrate()
    {
      if (false === $this->started) {
          $this->start();
      }
      
      parent::migrate();
    }

    /**
     * Gets the flash messages.
     *
     * @return array
     */
    public function getFlashes()
    {
      if (false === $this->started) {
          $this->start();
      }
      
      return parent::getFlashes();
    }

    /**
     * Gets a flash message.
     *
     * @param string      $name
     * @param string|null $default
     *
     * @return string
     */
    public function getFlash($name, $default = null)
    {
      if (false === $this->started) {
          $this->start();
      }
      
      return parent::getFlash($name, $default);
    }

}