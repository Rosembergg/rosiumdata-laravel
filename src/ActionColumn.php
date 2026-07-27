<?php

namespace Rosiumdata\Laravel;

class ActionColumn
{
    private string $key;
    private array $actions;
    private string $label;

    /**
     * @param string $key Unique key for the action column (e.g. 'actions')
     * @param array<int, array{key: string, label: string, danger?: bool}> $actions
     */
    public function __construct(string $key, array $actions)
    {
        $this->key = $key;
        $this->actions = $actions;
        $this->label = 'Actions';
    }

    /**
     * @param string $key Unique key for the action column
     * @param array<int, array{key: string, label: string, danger?: bool}> $actions
     */
    public static function make(string $key, array $actions): self
    {
        return new self($key, $actions);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'type' => 'action',
            'label' => $this->label,
            'sortable' => false,
            'filterable' => false,
            'visible' => true,
            'options' => [
                'actions' => $this->actions,
            ],
        ];

        return $data;
    }
}
