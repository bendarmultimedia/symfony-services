<?php

namespace App\Service\UI;

use App\Entity\ImportedFiles;
use App\Repository\AbsenceKindRepository;
use App\Repository\ImportedFilesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PageService
{
    private $title;
    private $template;
    private $templateDir;
    private $currentRoute;
    private $returnPath;

    public function __construct(
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGrenerator
    ) {
        $this->currentRoute = $requestStack->getCurrentRequest()->get('_route');
        $referer = $requestStack->getCurrentRequest()->headers->get('referer');
        $this->refererUrl = (strlen($referer) > 0) ? Request::create($referer)->getPathInfo() : '';
        $this->addReferer = false;
        $this->urlGrenerator = $urlGrenerator;
    }
    public function setTitle(string $title)
    {
        $this->title = $title;
    }
    public function setTemplateDir(string $templateDir)
    {
        $this->templateDir = $templateDir;
    }
    public function setAttributes(array $attributes)
    {
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (property_exists($this, $key)) {
                    if ($key == 'templateDir') {
                        $this->setTemplateDir($value);
                        $this->setTemplate();
                    } elseif ($key == 'returnPath') {
                        $this->setReturnPath($value);
                    } else {
                        $this->$key = $value;
                    }
                }
            }
        }
    }
    // todo: make it better
    public function setReturnPath(array $returnPath)
    {
        $this->returnPath['pathName'] = (isset($returnPath[0]))
            ? $returnPath[0]
            : '';
        $this->returnPath['buttonText'] = (isset($returnPath[1]))
            ? $returnPath[1]
            : 'Wróć';

        $this->returnPath['buttonText'] = (isset($returnPath[1]))
            ? $returnPath[1]
            : 'Wróć';
        $this->returnPath['buttonIcon'] = (isset($returnPath[2]))
            ? $returnPath[2]
            : '<i class="fas fa-angle-left"></i>';

        if ($this->addReferer) {
            $this->returnPath['url'] = $this->refererUrl;
        } else {
            $this->returnPath['url'] = $this->urlGrenerator->generate($this->returnPath['pathName']);
        }
    }

    public function setTemplate($templateFileName = null)
    {
        $this->template = ($templateFileName)
            ? $this->templateDir . '/' . $templateFileName
            : $this->templateDir . '/' . $this->currentRoute . '.html.twig';
            // dd($this->template);
    }
    public function getTitle()
    {
        return $this->title;
    }
    public function getReturnPath()
    {
        return $this->returnPath;
    }
    public function getTemplate()
    {
        return $this->template;
    }
    public function getTemplateDir()
    {
        return $this->templateDir;
    }
}
