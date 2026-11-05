<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Generador de campos de formulario con etiqueta, valor anterior, error y ayuda.
 * Uso en plantillas: Form::bind($old, $errors); echo Form::input('name', 'Nombre');
 */
final class Form
{
    private static array $old = [];
    private static array $errors = [];

    public static function bind(array $old, array $errors): void
    {
        self::$old = $old;
        self::$errors = $errors;
    }

    public static function value(string $name, mixed $default = ''): string
    {
        $v = self::$old[$name] ?? $default;
        return $v === null ? '' : (string) $v;
    }

    public static function error(string $name): ?string
    {
        return self::$errors[$name] ?? null;
    }

    /** @param array{hint?:string,class?:string,attrs?:array,value?:mixed} $o */
    public static function input(string $name, string $label, string $type = 'text', array $o = []): string
    {
        $value = $type === 'password' ? '' : self::value($name, $o['value'] ?? '');
        $control = '<input class="input' . (self::error($name) ? ' input-error' : '') . '" type="' . e($type)
            . '" id="' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '"' . self::attrs($o['attrs'] ?? []) . '>';
        return self::wrap($name, $label, $control, $o);
    }

    public static function textarea(string $name, string $label, array $o = []): string
    {
        $control = '<textarea class="input' . (self::error($name) ? ' input-error' : '') . '" id="' . e($name) . '" name="' . e($name)
            . '" rows="' . (int) ($o['rows'] ?? 3) . '"' . self::attrs($o['attrs'] ?? []) . '>' . e(self::value($name, $o['value'] ?? '')) . '</textarea>';
        return self::wrap($name, $label, $control, $o);
    }

    /** @param array<string,string> $options valor => texto */
    public static function select(string $name, string $label, array $options, array $o = []): string
    {
        $current = self::value($name, $o['value'] ?? '');
        $html = '<select class="input' . (self::error($name) ? ' input-error' : '') . '" id="' . e($name) . '" name="' . e($name) . '"' . self::attrs($o['attrs'] ?? []) . '>';
        foreach ($options as $val => $text) {
            $html .= '<option value="' . e($val) . '"' . ((string) $val === $current ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        return self::wrap($name, $label, $html . '</select>', $o);
    }

    /** Casilla que siempre envía 0 o 1 */
    public static function checkbox(string $name, string $label, array $o = []): string
    {
        $checked = self::value($name, $o['value'] ?? '0') === '1';
        $html = '<div class="' . e($o['class'] ?? '') . '"><label class="flex items-start gap-3 text-sm">'
            . '<input type="hidden" name="' . e($name) . '" value="0">'
            . '<input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-brand" id="' . e($name) . '" name="' . e($name) . '" value="1"'
            . ($checked ? ' checked' : '') . self::attrs($o['attrs'] ?? []) . '>'
            . '<span><span class="font-medium text-slate-700 dark:text-slate-200">' . e($label) . '</span>';
        if (!empty($o['hint'])) {
            $html .= '<span class="block text-xs text-slate-500 dark:text-slate-400">' . e($o['hint']) . '</span>';
        }
        return $html . '</span></label></div>';
    }

    /**
     * Campo de archivo con vista previa del actual y casilla para quitarlo.
     * @param ?string $currentUrl URL del archivo actual
     */
    public static function file(string $name, string $label, ?string $currentUrl, array $o = []): string
    {
        $isImage = $currentUrl && !str_ends_with(parse_url($currentUrl, PHP_URL_PATH) ?? '', '.pdf');
        $html = '';
        if ($currentUrl) {
            $html .= '<div class="mb-2 flex items-center gap-3 rounded-lg border border-dashed border-slate-300 p-2 dark:border-slate-700">';
            $html .= $isImage
                ? '<img src="' . e($currentUrl) . '" alt="" class="h-14 max-w-[10rem] rounded bg-white object-contain p-1">'
                : '<a href="' . e($currentUrl) . '" target="_blank" rel="noopener" class="text-sm text-brand underline">Ver PDF actual</a>';
            $html .= '<label class="ml-auto flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">'
                . '<input type="checkbox" name="remove_' . e($name) . '" value="1" class="h-4 w-4 accent-red-600"> Quitar</label></div>';
        }
        $html .= '<input type="file" id="' . e($name) . '" name="' . e($name) . '" accept="' . e($o['accept'] ?? '') . '" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">';
        return self::wrap($name, $label, $html, $o);
    }

    private static function wrap(string $name, string $label, string $control, array $o): string
    {
        $html = '<div class="' . e($o['class'] ?? '') . '"><label class="label" for="' . e($name) . '">' . e($label) . '</label>' . $control;
        if ($err = self::error($name)) {
            $html .= '<p class="field-error">' . e($err) . '</p>';
        } elseif (!empty($o['hint'])) {
            $html .= '<p class="hint">' . e($o['hint']) . '</p>';
        }
        return $html . '</div>';
    }

    private static function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $k => $v) {
            $out .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
        }
        return $out;
    }
}
