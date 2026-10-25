<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\PasswordInput;
use AIC\Component\Domain\RendersMarkup;

/**
 * PasswordInput's markup, compiled from
 * framework/templates/components/password-input.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class PasswordInputTemplate
{
    use RendersMarkup;

    /**
     * @param PasswordInput $passwordInput The component.
     *
     * @return string Its markup.
     */
    public function render(PasswordInput $passwordInput): string
    {
        return $this->part2($passwordInput);
    }

    /**
     * @param PasswordInput $passwordInput The component.
     *
     * @return string Markup from framework/templates/components/password-input.tpl.php.
     */
    private function part0(PasswordInput $passwordInput): string
    {
        return ' id="'
            . self::text($passwordInput->id)
            . '"';
    }

    /**
     * @param PasswordInput $passwordInput The component.
     *
     * @return string Markup from framework/templates/components/password-input.tpl.php.
     */
    private function part1(PasswordInput $passwordInput): string
    {
        return ' name="'
            . self::text($passwordInput->name)
            . '"';
    }

    /**
     * @param PasswordInput $passwordInput The component.
     *
     * @return string Markup from framework/templates/components/password-input.tpl.php.
     */
    private function part2(PasswordInput $passwordInput): string
    {
        return '<input type="password"'
            . ($passwordInput->id !== ''
                ? $this->part0($passwordInput)
                : '')
            . ($passwordInput->name !== ''
                ? $this->part1($passwordInput)
                : '')
            . ' autocomplete="current-password" class="aic-input">';
    }
}
