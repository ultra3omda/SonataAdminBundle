<?php

/*
 * This file is part of sonata-project.
 *
 * (c) 2010 Thomas Rabaix
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Twig\Extension;

use Sonata\AdminBundle\Admin\FieldDescriptionInterface;
use Sonata\AdminBundle\Filter\FilterInterface;
use Symfony\Component\Form\FormView;

class SonataAdminExtension extends \Twig_Extension
{
    /**
     * @var \Twig_Environment
     */
    protected $environment;

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array(
            'render_list_element'    => new \Twig_Filter_Method($this, 'renderListElement', array('is_safe' => array('html'))),
            'render_form_element'    => new \Twig_Filter_Method($this, 'renderFormElement', array('is_safe' => array('html'))),
            'render_filter_element'  => new \Twig_Filter_Method($this, 'renderFilterElement', array('is_safe' => array('html'))),
            'render_view_element'    => new \Twig_Filter_Method($this, 'renderViewElement', array('is_safe' => array('html'))),
        );
    }

    public function getTokenParsers()
    {
        return array();
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'sonata_admin';
    }

    /**
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param string $default
     * @return \Twig_TemplateInterface
     */
    protected function getTemplate(FieldDescriptionInterface $fieldDescription, $default)
    {
        // todo: find a better solution
        try {
            $template = $this->environment->loadTemplate($fieldDescription->getTemplate());
        } catch(\Twig_Error_Loader $e) {
            $template = $this->environment->loadTemplate($default);
        }

        return $template;
    }

    /**
     * render a list element from the FieldDescription
     *
     * @param mixed $object
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param array $params
     * @return
     */
    public function renderListElement($object, FieldDescriptionInterface $fieldDescription, $params = array())
    {
        $template = $this->getTemplate($fieldDescription, 'SonataAdminBundle:CRUD:base_list.html.twig');

        return $this->output($fieldDescription, $template, array_merge($params, array(
            'admin'  => $fieldDescription->getAdmin(),
            'object' => $object,
            'value'  => $this->getValueFromFieldDescription($object, $fieldDescription),
            'field_description' => $fieldDescription
        )));
    }

    /**
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param string $content
     * @return string
     */
    public function output(FieldDescriptionInterface $fieldDescription, \Twig_TemplateInterface $template, array $parameters = array())
    {
        $content = $template->render($parameters);

        if ($this->environment->isDebug()) {
            return sprintf("\n<!-- START  \n  fieldName: %s\n  template: %s\n  compiled template: %s\n -->\n%s\n<!-- END - fieldName: %s -->",
                $fieldDescription->getFieldName(),
                $fieldDescription->getTemplate(),
                $template->getTemplateName(),
                $content,
                $fieldDescription->getFieldName()
            );
        }

        return $content;
    }

    /**
     * return the value related to FieldDescription, if the associated object does no
     * exists => a temporary one is created
     *
     * @param object $object
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param array $params
     * @return mixed
     */
    public function getValueFromFieldDescription($object, FieldDescriptionInterface $fieldDescription, array $params = array())
    {
        if (isset($params['loop']) && $object instanceof \ArrayAccess) {
            throw new \RuntimeException('remove the loop requirement');
        }

        $value = $fieldDescription->getValue($object);

        // no value defined, check if the fieldDescription point to an association
        // if so, create an empty object instance
        // fixme: not sure this is the best place to do that
        if (!$value && $fieldDescription->getAssociationAdmin()) {
            $value = $fieldDescription->getAssociationAdmin()->getNewInstance();
        }

        return $value;
    }

    /**
     * render a filter element
     *
     * @param \Sonata\AdminBundle\Filter\FilterInterface $filter
     * @param array $params
     * @return string
     */
    public function renderFilterElement(FilterInterface $filter, array $params = array())
    {
        $fieldDescription = $filter->getFieldDescription();

        $template = $this->getTemplate($fieldDescription, 'SonataAdminBundle:CRUD:base_filter_field.html.twig');

        return $this->output($fieldDescription, $template, array_merge($params, array(
            'filter'        => $filter,
            'filter_form'   => $filter->getField()->createView()
        )));
    }

    /**
     * render a view element
     *
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param mixed $object
     * @return string
     */
    public function renderViewElement(FieldDescriptionInterface $fieldDescription, $object)
    {
        $template = $this->getTemplate($fieldDescription, 'SonataAdminBundle:CRUD:base_view_field.html.twig');

        return $this->output($fieldDescription, $template, array(
            'field_description' => $fieldDescription,
            'object'            => $object,
            'value'             => $fieldDescription->getValue($object)
        ));
    }

    /**
     * render a field element from the FieldDescription
     *
     * @throws InvalidArgumentException
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     * @param \Sumfony\Component\Form\FormView $formView
     * @param mixed $object
     * @param array $params
     * @return string
     */
    public function renderFormElement(FieldDescriptionInterface $fieldDescription, FormView $formView, $object, $params = array())
    {
        if (!$fieldDescription->getFieldName()) {
            return '';
        }

        if (!$formView->offsetExists($fieldDescription->getFieldName())) {
            return;
        }

        $children = $formView->offsetGet($fieldDescription->getFieldName());

        if (in_array('hidden', $children->get('types'))) {
            return '';
        }

        // find the correct edit parameter
        //  edit   : standard | inline
        //  inline : natural | table
        $parentFieldDescription = $fieldDescription->getAdmin()->getParentFieldDescription();

        if (!$parentFieldDescription) {
            $params['edit']          = $fieldDescription->getOption('edit', 'standard');
            $params['inline']        = $fieldDescription->getOption('inline', 'natural');

            $base_template = sprintf('SonataAdminBundle:CRUD:base_%s_edit_field.html.twig', 'standard');
        } else {
            $params['edit']          = $parentFieldDescription->getOption('edit', 'standard');
            $params['inline']        = $parentFieldDescription->getOption('inline', 'natural');

            $base_template = sprintf('SonataAdminBundle:CRUD:base_%s_edit_field.html.twig', $params['edit']);
        }

        $template = $this->getTemplate($fieldDescription, 'SonataAdminBundle:CRUD:base_standard_edit_field.html.twig');

        return $this->output($fieldDescription, $template, array_merge($params, array(
            'admin'             => $fieldDescription->getAdmin(),
            'object'            => $object,
            'field_description' => $fieldDescription,
            'value'             => $this->getValueFromFieldDescription($object, $fieldDescription, $params),
            'field_element'     => $children,
            'base_template'     => $fieldDescription->getOption('base_template', $base_template)
        )));
    }
}
