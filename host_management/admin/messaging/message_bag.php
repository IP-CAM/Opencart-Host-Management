<?php

namespace Opencart\Extension\HostManagement\Admin\Messaging;

use Opencart\System\Library\Language;


/**
 * Message bag.
 */
class MessageBag
{
    /**
     * Language instance.
     *
     * @var Language
     */
    protected $language;

    /**
     * Error messages.
     *
     * @var array
     */
    protected $error_msg = [];

    /**
     * Success messages.
     *
     * @var array
     */
    protected $success_msg = [];


    /**
     * Creates a new instance.
     *
     * @param Language $language
     */
    public function __construct(Language $language)
    {
        $this->language = $language;
    }

    /**
     * Translates message and applies replacements.
     *
     * @param string $message
     * @param string|int|float ...$replacements
     * @return string
     */
    protected function prepare(string $message, string|int|float ...$replacements): string
    {
        $message = $this->language->get($message);

        if (!empty($replacements)) {
            $replacements =
                array_map(fn($term) => lcfirst($this->language->get($term)), $replacements);
            $message = sprintf($message, ...$replacements);
        }

        return $message;
    }

    /**
     * Inserts error message into messages collection.
     *
     * @param string $message
     * @param string $key
     * @param string|int|float ...$replacements
     * @return void
     */
    public function error(
        string $message,
        string $key = 'warning',
        string|int|float ...$replacements
    ): void
    {
        $message = $this->prepare($message, ...$replacements);

        if (isset($this->error_msg[$key])) {
            $this->error_msg[$key] .= ' ' . $message;

            return;
        }

        $this->error_msg[$key] = $message;
    }

    /**
     * Merge error messages into this bag.
     *
     * @param array $errors
     * @return void
     */
    public function mergeErrors(array $errors): void
    {
        foreach ($errors as $key => $error) {
            if (isset($this->error_msg[$key])) {
                $this->error_msg[$key] .= ' ' . $error;

                continue;
            }

            $this->error_msg[$key] = $error;
        }
    }

    /**
     * Inserts success message into messages collection.
     *
     * @param string $message
     * @param string|int|float ...$replacements
     * @return void
     */
    public function success(string $message, string|int|float ...$replacements): void
    {
        $this->success_msg[] = $this->prepare($message, ...$replacements);
    }

    /**
     * Checks if there are error messages.
     *
     * @return boolean
     */
    public function hasErrors(): bool
    {
        return !empty($this->error_msg);
    }

    /**
     * Gets all messages.
     *
     * @return array
     */
    public function get(): array
    {
        $messages = [];

        if ($this->hasErrors()) {
            $messages['error'] = $this->error_msg;
        }

        if (!empty($this->success_msg)) {
            $messages['success'] = implode(' ', $this->success_msg);
        }

        return $messages;
    }

    /**
     * Gets error messages.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->error_msg;
    }

    /**
     * Gets first error message.
     *
     * @return string
     */
    public function getFirstError(): string
    {
        return reset($this->error_msg) ?: '';
    }
}