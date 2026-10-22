<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\PasswordInput;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\Textarea;
use AIC\Component\Exception\UnknownComponent;
use Override;

/**
 * The markup of the catalog's form controls (#98): a text input, a password,
 * a textarea, a checkbox and a file input. The form atoms' templates follow
 * it.
 */
final readonly class ControlTemplates implements ComponentTemplates
{
    use RendersMarkup;

    /**
     * @param ComponentTemplates $componentTemplates The form atoms' templates.
     */
    public function __construct(
        private ComponentTemplates $componentTemplates = new FormTemplates(),
    ) {
    }

    /**
     * @param Component  $component  A component.
     * @param RenderPass $renderPass The pass rendering it.
     *
     * @throws UnknownComponent When no template renders it.
     *
     * @return string Its markup.
     */
    #[Override]
    public function compose(Component $component, RenderPass $renderPass): string
    {
        if ($component instanceof Input) {
            return $this->input($component);
        }

        if ($component instanceof PasswordInput) {
            return $this->password($component);
        }

        if ($component instanceof Textarea) {
            return $this->textarea($component);
        }

        if ($component instanceof Checkbox) {
            return $this->checkbox($component);
        }

        if ($component instanceof FileInput) {
            return $this->file($component);
        }

        return $this->componentTemplates->compose($component, $renderPass);
    }

    /**
     * @param Input $input A text input.
     *
     * @return string `<input class="aic-input">`.
     */
    private function input(Input $input): string
    {
        return '<input' . self::attribute('id', $input->id) . self::attribute('name', $input->name)
            . ' value="' . self::text($input->value) . '"' . ($input->invalid ? ' aria-invalid="true"' : '') . ' class="aic-input">';
    }

    /**
     * @param PasswordInput $passwordInput A password input.
     *
     * @return string `<input type="password">`.
     */
    private function password(PasswordInput $passwordInput): string
    {
        return '<input type="password"' . self::attribute('id', $passwordInput->id) . self::attribute('name', $passwordInput->name)
            . ' autocomplete="current-password" class="aic-input">';
    }

    /**
     * @param Textarea $textarea A textarea.
     *
     * @return string `<textarea class="aic-textarea">`.
     */
    private function textarea(Textarea $textarea): string
    {
        return '<textarea' . self::attribute('id', $textarea->id) . self::attribute('name', $textarea->name)
            . ' class="aic-textarea">' . self::text($textarea->value) . '</textarea>';
    }

    /**
     * @param Checkbox $checkbox A checkbox.
     *
     * @return string `<input type="checkbox">`.
     */
    private function checkbox(Checkbox $checkbox): string
    {
        return '<input type="checkbox"' . self::attribute('id', $checkbox->id) . self::attribute('name', $checkbox->name)
            . ' value="1"' . ($checkbox->checked ? ' checked' : '') . ' class="aic-checkbox">';
    }

    /**
     * @param FileInput $fileInput A file input.
     *
     * @return string `<input type="file">`.
     */
    private function file(FileInput $fileInput): string
    {
        return '<input type="file"' . self::attribute('id', $fileInput->id) . self::attribute('name', $fileInput->name)
            . self::attribute('accept', $fileInput->accept) . ' class="aic-input">';
    }
}
