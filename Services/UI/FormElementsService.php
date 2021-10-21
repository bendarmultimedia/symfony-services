<?php

namespace App\Service\UI;

use Twig\Environment;

class FormElementsService
{
    private $twig;
    private $templates;

    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
        $this->templates = [
            'select'      =>  'UI/Forms/select.form.html.twig',
            'input'       =>  'UI/Forms/input.html.twig',
            'textarea'    =>  'UI/Forms/textarea.html.twig',
            'button'      =>  'UI/Forms/button.html.twig',
            'alert'       =>  'UI/Forms/alert.html.twig',
            'checkbox'    =>  'UI/Forms/checkbox.html.twig',
            'radios'      =>  'UI/Forms/radios.html.twig',
        ];
    }

    public function createSelect(array $params = [], array $options = null, $render = true)
    {
        $default = [
           'id' => 'newSelect',
           'name' => 'newSelect',
           'containerClassNames' => 'form-group',
           'container' => true,
           'classNames' => 'form-control rounded',
           'labelClassNames' => 'form-label',
           'label' => 'Pole Wyboru',
           'choosen' => [],
           'clearButton'  => false,
           'attributes' => [
                'data-size' => "8"
           ],
           'options'    => []
        ];
        $select = changeArrayByArray($default, $params);
        if (is_array($options) && count($options) > 0) {
            $select['options'] = $options;
        }
        return $this->returnFormElement('select', $select, $render);
    }

    public function createRadios(array $params = [], array $options = null, $render = true)
    {
        $default = [
           'id' => 'newRadios',
           'name' => 'newRadios',
           'containerClassNames' => '',
           'container' => true,
           'classNames' => ' custom-control custom-checkbox custom-control-inline',
           'inputClassNames' => 'custom-control-input',
           'labelClassNames' => 'custom-control-label checkbox-primary',
           'description' => 'Radios',
           'choosen' => [],
           'clearButton'  => false,
           'attributes' => [],
           'options'    => []
        ];
        $radios = changeArrayByArray($default, $params);
        if (is_array($options) && count($options) > 0) {
            $radios['options'] = $options;
        }
        return $this->returnFormElement('radios', $radios, $render);
    }

    public function createInput(string $type = 'text', array $params = [], $render = true)
    {
        $default = [
           'id' => 'newInput',
           'name' => 'newInput',
           'containerClassNames' => 'form-group',
           'container' => true,
           'classNames' => 'form-control rounded',
           'labelClassNames' => 'form-label',
           'label' => 'Pole Formularza',
           'labelAfter' => false,
           'type' => $type,
           'disableLabel'   => false,
           'value'  => '',
           'attributes' => [],
        ];
        $input = changeArrayByArray($default, $params);
        return $this->returnFormElement('input', $input, $render);
    }

    public function createCheckbox(array $params = [], $render = true)
    {
        $default = [
           'id' => 'newCheckbox',
           'name' => 'newCheckbox',
           'containerClassNames' => 'form-group',
           'container' => true,
           'classNames' => 'custom-control-input',
           'labelClassNames' => 'custom-control-label checkbox-success outline',
           'label' => 'Pole Formularza',
           'checked' => false,
           'value'  => '',
           'attributes' => [],
        ];
        $input = changeArrayByArray($default, $params);
        return $this->returnFormElement('checkbox', $input, $render);
    }

    public function createTextArea(array $params = [], $render = true)
    {
        $default = [
           'id' => 'newTextArea',
           'name' => 'newTextArea',
           'containerClassNames' => 'form-group',
           'container' => true,
           'classNames' => 'form-control border-0 p-2 w-100 h-100',
           'labelClassNames' => 'form-label',
           'label' => 'Pole Tekstowe',
           'value'  => null,
           'attributes' => [],
        ];
        $textarea = changeArrayByArray($default, $params);
        return $this->returnFormElement('textarea', $textarea, $render);
    }

    public function createButton(array $params = [], $render = true)
    {
        $default = [
           'id' => 'newButton',
           'containerClassNames' => 'container',
           'container' => false,
           'classNames' => 'btn btn-primary',
           'text' => 'Przycisk',
           'attributes' => [],
        ];
        $button = changeArrayByArray($default, $params);
        return $this->returnFormElement('button', $button, $render);
    }

    public function createAlert(array $params = [], $render = true)
    {
        $default = [
           'id' => '',
           'containerClassNames' => 'container',
           'container' => false,
           'classNames' => 'alert-info',
           'text' => 'Komunikat',
           'attributes' => [],
        ];
        $alert = changeArrayByArray($default, $params);
        return $this->returnFormElement('alert', $alert, $render);
    }

    /*
    * @return array - [inputType, :optional step]
    */
    public static function guessInputType(string $type, $step = false): array
    {
        switch ($type) {
            case "int":
                return ($step) ? ['number', 1] : ['number'];
                break;
            case "float":
                return ($step) ? ['number', 0.01] : ['number'];
                break;
            case "string":
                return ($step) ? ['text'] : ['text'];
                break;
            case "text":
                return ($step) ? ['text'] : ['text'];
                break;
            case "date":
                return ($step) ? ['date'] : ['date'];
                break;
            case "datetime":
                return ($step) ? ['datetime-local'] : ['datetime-local'];
                break;
            case "bool":
                return ($step) ? ['checkbox'] : ['checkbox'];
                break;
            default:
                return null;
        }
    }

    public static function createValuesFromEntities(array $items): array
    {
        $values = [];
        foreach ($items as $item) {
            $values[] = ['name' => $item->getName(), 'value' => $item->getId()];
        }
        return $values;
    }

    private function returnFormElement(string $elementTypeName, array $element, bool $render)
    {
        $elementTypes = ['select', 'input', 'textarea', 'button', 'alert', 'checkbox', 'radios'];

        if (!in_array($elementTypeName, $elementTypes)) {
            throw new \Exception(
                'Wrong form element type'
            );
        }

        return ($render)
            ? $this->twig->render(
                $this->templates[$elementTypeName],
                [
                    $elementTypeName => $element,
                ]
            )
            : $element;
    }
}
