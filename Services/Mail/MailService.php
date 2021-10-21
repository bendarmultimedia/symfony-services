<?php

namespace App\Service\Mail;

use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class MailService
{
    protected Environment $twig;
    protected MailerInterface $mailer;
    protected string $templateDir;
    protected string $template;
    protected string $defaultSubject;
    protected Email $email;
    protected TemplatedEmail $templatedEmail;
    protected $devMail;
    protected string $env;

    public function __construct(
        Environment $twig,
        MailerInterface $mailer,
        UrlGeneratorInterface $router,
        KernelInterface $kernel
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->router = $router;
        $this->env = $kernel->getEnvironment() ;

        $this->templateDir = 'mail';
        $this->from = new Address('patryk@bendar.eu', 'Patryk Cieślak');
        $this->defaultSubject = 'Message from App';
        $this->setTemplate('default/mail.html.twig');
        $this->checkDevMail();
    }

    public function checkDevMail()
    {
        if ($this->env == 'dev') {
            $this->devMail = (isset($_SERVER['DEV_EMAIL_ADDRESS'])) ? $_SERVER['DEV_EMAIL_ADDRESS'] : '' ;
            if (!filter_var($this->devMail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(
                    "Set the correct email address for DEV_EMAIL_ADDRESS const in .env file for dev environment.",
                    500
                );
            }
        }
    }

    public function sendEmail(
        string $to,
        string $subject,
        string $html = null,
        string $text = null,
        $from = null,
        array $filesToAttach = []
    ) {
        $this->setEmail([
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'from' => $from,
        ]);
        $from = ($from) ? $from : $this->from;

        if (count($filesToAttach) >= 1) {
            foreach ($filesToAttach as $key => $filePath) {
                $this->templatedEmail->attachFromPath($filePath);
            }
        }
        return $this->mailer->send($this->email);
    }

    public function sendTemplatedEmail(
        string $to,
        string $subject = null,
        string $htmlContent = null,
        string $from = null,
        array $params = [],
        array $filesToAttach = []
    ) {
        $from = ($from) ? $from : $this->from;
        $subject = ($subject) ? $subject : $this->defaultSubject;

        $this->setTemplatedEmail(
            [
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlContent,
                'from' => $from,
            ],
            $params
        );

        if (count($filesToAttach) >= 1) {
            foreach ($filesToAttach as $key => $filePath) {
                $this->templatedEmail->attachFromPath($filePath);
            }
        }
        return $this->send($this->templatedEmail);
    }

    public function render($templatePath, $elements = [])
    {
        return $this->twig->render(
            $templatePath,
            $elements
        );
    }

    public function send($email = null)
    {
        $email = ($email) ? $email : $this->templatedEmail;
        $email = ($email) ? $email : $this->email;
        $this->mailer->send($email);
    }

    public static function setAlert(string $type, $text): string
    {
        $style = 'padding: 10px 8px; margin: 4px; border: 1px solid; border-radius: 6px;';
        switch ($type) {
            case 'success':
                $style .= ' color: #155724; background-color: #d4edda; border-color: #c3e6cb;';
                break;
            case 'warning':
                $style .= ' color: #856404; background-color: #fff3cd; border-color: #ffeeba;';
                break;
            case 'danger':
                $style .= ' color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;';
                break;
            case 'primary':
                $style .= ' color: #004085; background-color: #cce5ff; border-color: #b8daff;';
                break;
            default:
                $style .= ' color: #004085; background-color: #cce5ff; border-color: #b8daff;';
                break;
        }

        return '<p style="' . $style . '">' . $text . '</p>';
    }

    /**
     * Get the value of templateDir
     */
    public function getTemplateDir()
    {
        return $this->templateDir;
    }

    /**
     * Set the value of templateDir
     *
     * @return  self
     */
    public function setTemplateDir($templateDir)
    {
        $this->templateDir = $templateDir;

        return $this;
    }

    /**
     * Get the value of template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set the value of template
     *
     * @return  self
     */
    public function setTemplate($templateFileName)
    {
        $this->template = $this->getTemplateDir() . "/" . $templateFileName;

        return $this;
    }

    /**
     * Get the value of email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the value of email
     *
     * @return  Email
     */
    public function setEmail(array $emailProps)
    {
        $this->email = new Email();
        foreach ($emailProps as $propName => $propValue) {
            if ($propValue) {
                $this->email->$propName($propValue);
            }
        }

        if ($this->env == 'dev' && strlen($this->devMail) > 0) {
            $this->email->to($this->devMail);
        }
        return $this->email;
    }

    /**
     * Get the value of email
     */
    public function getTemplatedEmail()
    {
        return $this->templatedEmail;
    }

    /**
     * Set the value of email
     *
     * @return  Email
     */
    public function setTemplatedEmail(array $emailProps, $params = [])
    {
        $this->templatedEmail = new TemplatedEmail();
        foreach ($emailProps as $propName => $propValue) {
            if ($propValue) {
                $this->templatedEmail->$propName($propValue);
            }
        }
        if ($this->template) {
            $this->templatedEmail->htmlTemplate($this->template);
        }
        $params = (count($params) > 0) ? $params : $emailProps;
        $params['subject'] = $emailProps['subject'];
        $params['html'] = $emailProps['html'];


        $this->templatedEmail->context($params);
        if ($this->env == 'dev' && strlen($this->devMail) > 0) {
            $this->templatedEmail->to($this->devMail);
        }
        return $this->templatedEmail;
    }

    protected function setUrlFromRouteName($routeName, $params = []): string
    {
        return $this->router->generate(
            $routeName,
            $params,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
