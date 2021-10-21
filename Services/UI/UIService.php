<?php

namespace App\Service\UI;

class UIService
{
    private PageService $page;
    private FormElementsService $forms;

    public function __construct(
        PageService $page,
        FormElementsService $forms
    ) {
        $this->page = $page;
        $this->forms = $forms;
    }

    /**
     * Get the value of page
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Get the value of forms
     */
    public function getForms()
    {
        return $this->forms;
    }
}
