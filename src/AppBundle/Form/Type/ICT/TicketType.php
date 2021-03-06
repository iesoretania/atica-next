<?php
/*
  Copyright (C) 2018: Luis Ramón López López

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see [http://www.gnu.org/licenses/].
*/

namespace AppBundle\Form\Type\ICT;

use AppBundle\Entity\ICT\Priority;
use AppBundle\Entity\ICT\Ticket;
use AppBundle\Entity\Location;
use AppBundle\Service\UserExtensionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketType extends AbstractType
{
    private $userExtensionService;

    private $entityManager;

    public function __construct(UserExtensionService $userExtensionService, EntityManagerInterface $locationRepository)
    {
        $this->userExtensionService = $userExtensionService;
        $this->entityManager = $locationRepository;
    }

    public function addElements(FormInterface $form, Location $location = null)
    {
        $elements = null === $location ? [] : $this->entityManager->getRepository('AppBundle:ICT\Element')->findByLocation($location);

        $placeholder = (count($elements) === 0) ? 'form.no_elements' : 'form.select_element';

        $locations = $this->entityManager->getRepository(Location::class)->findRootsByOrganization($this->userExtensionService->getCurrentOrganization());

        $form
            ->add('location', EntityType::class, [
                'label' => 'form.location',
                'class' => Location::class,
                'choice_translation_domain' => false,
                'choices' => $locations,
                'mapped' => false,
                'placeholder' => 'form.select_location'
            ])
            ->add('element', null, [
                'label' => 'form.element',
                'choice_translation_domain' => false,
                'choices' => $elements,
                'placeholder' => $placeholder
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.description',
                'attr' => [
                    'rows' => 8
                ]
            ]);

        if (true === $this->userExtensionService->isUserLocalAdministrator()) {
            $form
                ->add('priority', ChoiceType::class, [
                    'label' => 'form.priority',
                    'choices' => $this->entityManager->getRepository('AppBundle:ICT\Priority')->findAllSortedByPriority(),
                    'choice_label' => function(Priority $priority = null) {
                        return (null !== $priority) ? $priority->getName() : '';
                    },
                    'choice_value' => function(Priority $priority = null) {
                        return (null !== $priority) ? $priority->getLevelNumber() : '';
                    },
                    'placeholder' => 'form.select_priority'
                ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (true === $this->userExtensionService->isUserLocalAdministrator()) {
            $builder
                ->add('createdBy', null, [
                    'label' => 'form.created_by'
                ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            $location = $data->getElement() ? $data->getElement()->getLocation() : null;

            $this->addElements($form, $location);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $location = isset($data['location']) ? $this->entityManager->getRepository('AppBundle:Location')->find($data['location']) : null;

            $this->addElements($form, $location);
        });

    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
            'translation_domain' => 'ict_ticket',
            'new' => false
        ]);
    }
}
