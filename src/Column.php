<?php

namespace Rosiumdata\Laravel;

class Column
{
    private string $key;
    private string $type;
    private string $label;
    private ?string $mask = null;
    private bool $sortable;
    private bool $filterable;
    private bool $visible;
    private ?array $options = null;
    private ?string $alignment = null;

    public function __construct(string $key, string $type)
    {
        $this->key = $key;
        $this->type = $type;
        $this->label = $type === 'action' ? '' : $key;
        $this->sortable = $type !== 'action';
        $this->filterable = $type !== 'action';
        $this->visible = true;
    }

    public static function make(string $key, string $type): self
    {
        return new self($key, $type);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function mask(string $mask): self
    {
        $this->mask = $mask;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function filterable(bool $filterable = true): self
    {
        $this->filterable = $filterable;

        return $this;
    }

    public function visible(bool $visible = true): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Options for select columns — associative array [value => label].
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function alignment(string $alignment): self
    {
        $this->alignment = $alignment;

        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'filterable' => $this->filterable,
            'visible' => $this->visible,
        ];

        if ($this->mask !== null) {
            $data['mask'] = $this->mask;
        }

        if ($this->options !== null) {
            $data['options'] = $this->options;
        }

        if ($this->alignment !== null) {
            $data['alignment'] = $this->alignment;
        }

        return $data;
    }
}
