<?php

namespace AppBundle\Controller;

use CollectionBundle\Entity\Collection;
use MessageBundle\Entity\MailingList;
use MessageBundle\Entity\Message;
use MessageBundle\Form\MailingListType;
use MessageBundle\Form\MessageType;
use OrderBundle\Entity\Devis;
use OrderBundle\Entity\DevisItem;
use OrderBundle\Entity\OrderInfo;
use OrderBundle\Form\CommandeItemEditTypeClient1;
use OrderBundle\Form\DevisType;
use OrderBundle\Form\PersonalInfoType;
use ProductBundle\Entity\Product;
use ProductBundle\Entity\ProductVariation;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }

        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/index.html.twig', array(
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/products", name="index_products_page")
     */
    public function productAction(Request $request)
    {
        $test = $request->query->get('collection');

        $em = $this->getDoctrine()->getManager();
        $queryBuilder = $em->getRepository(Product::class)->createQueryBuilder('p');
        $queryBuilder->leftJoin('p.variations', 'variations')
            ->addSelect('variations');
        $queryBuilder->where('p.enabled = true');
        if($request->query->getAlnum('category')) {
            $queryBuilder->andWhere('p.category = :category')
                ->setParameter('category', $request->query->getAlnum('category'));
        }
        if($request->query->getAlnum('collection')) {
            $queryBuilder->leftJoin('p.collection', 'collection')
                ->addSelect('collection')
                ->andWhere('collection.name = :collection')
                ->setParameter('collection', $test);
        }

        $query = $queryBuilder->getQuery();
        $paginator = $this->get('knp_paginator');
        $result = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 9)
        );

        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/list_products.html.twig', array(
            'products' => $result,
            'mailing_form' => $mailing_form->createView(),
            'cartLogo' => $cartLogo,
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/products/{id}-{v_id}", name="product_details_page")
     */
    public function productDetailsAction(Request $request, $id, $v_id)
    {
        $em = $this->getDoctrine()->getManager();
        $product = $em->getRepository(Product::class)->find($id);
        $variation = $em->getRepository(ProductVariation::class)->find($v_id);
        $variations = $em->getRepository(ProductVariation::class)->findBy(['product' => $product]);

        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }

        $devisItem = new DevisItem();
        $cart_form = $this->get('form.factory')->create(CommandeItemEditTypeClient1::class, $devisItem);
        if($request->isMethod('POST') && $cart_form->handleRequest($request)->isValid()) {

            $devisItem->setProduct($product);
            $session = $this->get('session');

            if ($session->has('cartElements')) {
                //add to existing commande
                $devisJson = $session->get('cartElements');
                $devis = $serializer->deserialize($devisJson, Devis::class, 'json');
                $database_devis = $this->getDoctrine()->getManager()->getRepository(Devis::class)->find($devis->getId());
                $database_devis->addItem($devisItem);
                $devisItem->setDevis($database_devis);
                $em->persist($database_devis);
                $em->flush();
                $jsonDevis = $serializer->serialize($database_devis, 'json');
                //die(var_dump($jsonCommande));
                $session->set('cartElements', $jsonDevis);
            } else {
                $devis = new Devis();
                $devis->setEnabled(false);
                $devis->setSaleDate(new \DateTime());
                $em->persist($devis);
                $em->flush();
                $devis->addItem($devisItem);
                $devisItem->setDevis($devis);
                $em->persist($devisItem);
                //die(var_dump($devisItem));
                $em->flush();
                $jsonDevis = $serializer->serialize($devis, 'json');
                $session->set('cartElements', $jsonDevis);
            }
            return $this->redirectToRoute('index_products_page');
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/detail_product.html.twig', array(
            'p' => $product,
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'cart_form' => $cart_form->createView(),
            'collections' => $collections,
            'variation' => $variation,
            'variations' => $variations,
        ));
    }

    /**
     * @Route("/add-to-cart/{id}-{v_id}", name="add_to_cart_page")
     */
    public function addCartAction(Request $request, $id, $v_id)
    {
        $em = $this->getDoctrine()->getManager();
        $product = $em->getRepository(Product::class)->find($id);
        $variation = $em->getRepository(ProductVariation::class)->find($v_id);
        $serializer = $this->get('jms_serializer');

        $devisItem = new DevisItem();
        if($request->isMethod('POST')) {
            $devisItem->setProduct($product);
            $devisItem->setVariation($variation);
            $quantity = $request->request->get('quantity');
            if($quantity != null) {
                $devisItem->setQuantity($quantity);
            }
            else {
                $devisItem->setQuantity(1);
            }

            $size = $request->request->get('size-select');
            if($size != null) {
                $devisItem->setSize($size);
            }
            else {
                $devisItem->setSize('M');
            }

            $session = $this->get('session');

            if ($session->has('cartElements')) {
                //add to existing commande
                $devisJson = $session->get('cartElements');
                $devis = $serializer->deserialize($devisJson, Devis::class, 'json');
                $database_devis = $this->getDoctrine()->getManager()->getRepository(Devis::class)->find($devis->getId());
                $database_devis->addItem($devisItem);
                $devisItem->setDevis($database_devis);
                $em->persist($database_devis);
                $em->flush();
                $jsonDevis = $serializer->serialize($database_devis, 'json');
                //die(var_dump($jsonCommande));
                $session->set('cartElements', $jsonDevis);
            }
            else{
                $devis = new Devis();
                $devis->setEnabled(false);
                $devis->setSaleDate(new \DateTime());
                $em->persist($devis);
                $em->flush();
                $devis->addItem($devisItem);
                $devisItem->setDevis($devis);
                $em->persist($devisItem);
                //die(var_dump($devisItem));
                $em->flush();
                $jsonDevis = $serializer->serialize($devis, 'json');
                $session->set('cartElements', $jsonDevis);
            }

            return $this->redirectToRoute('index_products_page');
        }
        return $this->redirectToRoute('index_products_page');
    }

    /**
     * @Route("/cart-page", name="cart_page")
     */
    public function cartAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        if ($session->has('cartElements')) {
            $devisJson = $session->get('cartElements');
            $devis = $serializer->deserialize($devisJson, Devis::class, 'json');
            $em = $this->getDoctrine()->getManager();
            $database_commande = $em->getRepository(Devis::class)->find($devis->getId());
            $data = $devis->getItems();
            $commande_form = $this->get('form.factory')->create(DevisType::class, $database_commande);
            if ($request->isMethod('POST') && $commande_form->handleRequest($request)->isValid()) {
                //die(dump($data));
                $em->persist($database_commande);
                $em->flush();
                $jsonDevis = $serializer->serialize($database_commande, 'json');
                $session->set('cartElements', $jsonDevis);
                return $this->redirectToRoute('checkout_page');
            }
        }
        else {
            return $this->redirectToRoute('homepage');
        }

        /*$session->clear();
        die('cleared');*/

        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }

        //dump($data);
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/cart-page.html.twig', array(
            'commande_form' => $commande_form->createView(),
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'items1' => $data,
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/delete-cart-item-{id}", name="delete_cart_item")
     */
    public function deleteCartItemAction($id) {

        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');

        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');

            $database_commande = $this->getDoctrine()->getManager()->getRepository(Devis::class)->find($commande->getId());
            $item = $this->getDoctrine()->getManager()->getRepository(DevisItem::class)->find($id);
            $database_commande->removeItem($item);

            $em->remove($item);
            $em->flush();

            $jsonCommande = $serializer->serialize($database_commande, 'json');
            $session->set('cartElements', $jsonCommande);
        }
        return $this->redirectToRoute('cart_page');
    }

    /**
     * @Route("/checkout-page", name="checkout_page")
     */
    public function checkoutAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());

            $data = $commande->getItems();
            dump($data);

            $database_commande = $this->getDoctrine()->getManager()->getRepository(Devis::class)->find($commande->getId());
            if($database_commande->getOrderInfo()) {
                $personalinfo_form = $this->get('form.factory')->create(PersonalInfoType::class, $database_commande->getOrderInfo());
            }
            else {
                $personal_info = new OrderInfo();
                $database_commande->setOrderInfo($personal_info);
                $personalinfo_form = $this->get('form.factory')->create(PersonalInfoType::class, $personal_info);
            }

            if ($request->isMethod('POST') && $personalinfo_form->handleRequest($request)->isValid()) {
                $database_commande->setEnabled(true);
                $em = $this->getDoctrine()->getManager();

                foreach ($data as $devis_item) {
                    $variation = $em->getRepository(ProductVariation::class)->find(($devis_item->getVariation()->getId()));
                    switch ($devis_item->getSize()) {
                        case 'S':
                            $val = $variation->getS();
                            $val = $val - 1;
                            $variation->setS(strval($val));
                            break;
                        case 'M':
                            $val = $variation->getM();
                            $val = $val - 1;
                            $variation->setM($val);
                            break;
                        case 'L':
                            $val = $variation->getL();
                            $val = $val - 1;
                            $variation->setL($val);
                            break;
                        case 'XL':
                            $val = $variation->getXL();
                            $val = $val - 1;
                            $variation->setXL($val);
                            break;
                        case 'XXL':
                            $val = $variation->getXXL();
                            $val = $val - 1;
                            $variation->setXXL($val);
                            break;
                    }
                }
                $devis_item->setVariation($variation);
                //die(dump($devis_item));
                $em->flush();
                $session->clear();
                return $this->redirectToRoute('after_checkout');
            }
        }
        else {
            return $this->redirectToRoute('homepage');
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/validate_devis.html.twig', array(
            'personalinfo_form' => $personalinfo_form->createView(),
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'items1' => $data,
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/contact", name="contact_page")
     */
    public function contactAction(Request $request)
    {
        $message = new Message();
        $message->setEnabled(true);
        $form = $this->get('form.factory')->create(MessageType::class, $message);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($message);
            $em->flush();
            return $this->redirect($request->getUri());
        }

        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/contact.html.twig', array(
            'contact_form' => $form->createView(),
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/checkout", name="after_checkout")
     */
    public function afterCheckoutAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }

        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        return $this->render('default/after_checkout.html.twig', array(
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'collections' => $collections,
        ));
    }

    /**
     * @Route("/about-us", name="about-us")
     */
    public function aboutUs(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $session = $this->get('session');
        $cartLogo = 0;
        if ($session->has('cartElements')) {
            $commandeJson = $session->get('cartElements');
            $commande = $serializer->deserialize($commandeJson, Devis::class, 'json');
            $cartLogo = count($commande->getItems());
        }
        $collections = $this->getDoctrine()->getManager()->getRepository(Collection::class)->findBy(['enabled' => true]);
        $mailing = new MailingList();
        $mailing_form = $this->get('form.factory')->create(MailingListType::class, $mailing);
        if ($request->isMethod('POST') && $mailing_form->handleRequest($request)->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($mailing);
            $em->flush();
            return $this->redirectToRoute('homepage');
        }
        return $this->render('default/about-us.html.twig', array(
            'cartLogo' => $cartLogo,
            'mailing_form' => $mailing_form->createView(),
            'collections' => $collections,
        ));
    }

}
