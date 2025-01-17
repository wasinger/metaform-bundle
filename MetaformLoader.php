<?php
namespace Wasinger\MetaformBundle;

use Isometriks\Bundle\SpamBundle\Form\Extension\Spam\Type\FormTypeHoneypotExtension;
use Isometriks\Bundle\SpamBundle\Form\Extension\Spam\Type\FormTypeTimedSpamExtension;
use Wasinger\MetaformBundle\Exceptions\FormNotFoundException;
use Wasinger\MetaformBundle\Exceptions\FormParserException;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Yaml\Yaml;

class MetaformLoader
{
    /**
     * @var string
     */
    private $configdir;
    /**
     * @var string
     */
    private $cachedir;

    private $resources = [];

    /**
     * @var FormFactoryInterface
     */
    private $formfactory;

    private array $options = [
        'isometriks_spam_honeypot' => false,
        'isometriks_spam_timed' => false
    ];
    /**
     * YamlFormConfigurator constructor.
     *
     * @param string $configdir
     * @param string $cachedir
     * @param FormFactoryInterface $formfactory
     */
    public function __construct(string $configdir, string $cachedir, FormFactoryInterface $formfactory)
    {
        $this->configdir = $configdir;
        $this->cachedir = $cachedir;
        $this->formfactory = $formfactory;
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function listForms(): array
    {
        $forms = [];
        foreach (new \DirectoryIterator($this->configdir) as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $ext = $fileInfo->getExtension();
            if ($ext == 'yml' || $ext == 'yaml') {
                $forms[] = $fileInfo->getBasename('.' . $ext);
            }
        }
        return $forms;
    }

    /**
     * Load a metaform instance specified by $form_id
     *
     * @param string $form_id
     * @return Metaform
     */
    public function load(string $form_id): Metaform
    {
        if (!$form_id) throw new \InvalidArgumentException();
        $cachepath = $this->cachedir . '/formconfig/' . $form_id . '.php';
        $cache = new ConfigCache($cachepath, true);
        if (!$cache->isFresh()) {
            $config = $this->loadYamlConfiguration($form_id);
            $processor = new Processor();
            $formConfiguration = new MetaformConfiguration();
            $processedConfiguration = $processor->processConfiguration($formConfiguration, [$config]);

            $this->normalizeConfiguration($processedConfiguration);

            $cache->write('<?php return ' . \var_export($processedConfiguration, true) . ';', $this->resources);
        } else {
            $processedConfiguration = require $cachepath;
        }
        $fboptions = [];
        if ($this->options['isometriks_spam_honeypot'] && class_exists(FormTypeHoneypotExtension::class)) {
            $fboptions['honeypot'] = true;
        }
        if ($this->options['isometriks_spam_timed'] && class_exists(FormTypeTimedSpamExtension::class)) {
            $fboptions['timed_spam'] = true;
        }
        return new Metaform(
            $form_id,
            $processedConfiguration,
            $this->formfactory->createBuilder(FormType::class, null, $fboptions)
        );
    }

    private function normalizeConfiguration(array &$conf)
    {
        foreach ($conf['elements'] as $key => &$element) {
            $label = ($element['label'] ?? $element['options']['label'] ?? \ucfirst($key));
            $element['label'] = $element['options']['label'] = $label;

            $required = ($element['required'] ?? $element['options']['required'] ?? false);
            $element['required'] = $element['options']['required'] = $required;
        }
    }

    /**
     * @param string $form_id
     * @return array
     * @throws FormNotFoundException
     * @throws FormParserException
     */
    private function loadYamlConfiguration(string $form_id): array
    {
        $ymlfile = $this->configdir . DIRECTORY_SEPARATOR . filter_var($form_id, FILTER_SANITIZE_STRING) . '.yml';
        if (!class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            throw new \RuntimeException('package symfony/yaml not installed');
        }
        if (!file_exists($ymlfile)) {
            throw new FormNotFoundException('Form configuration file ' . $ymlfile . ' does not exist');
        }
        if (!is_readable($ymlfile)) {
            throw new FormParserException('Form configuration file ' . $ymlfile . ' is not readable');
        }
        $formconfig = Yaml::parse(file_get_contents($ymlfile));
        $this->resources[] = new FileResource($ymlfile);
        if (isset($formconfig['form'])) {
            $formconfig = $formconfig['form'];
        }

        return $formconfig;
    }
}