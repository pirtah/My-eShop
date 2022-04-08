<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Entity\Produit;
use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/mon-panier")
 */
class PanierController extends AbstractController
{
    /**
     * @Route("/voir-mon-panier", name="show_panier", methods={"GET"})
     * @param SessionInterface $session
     * @return Response
     */
    public function showPanier(SessionInterface $session): Response
    {
        //$session->remove('panier');
        $panier = $session->get('panier', []);
        $total = 0;

        foreach ($panier as $key => $item){
            $totalItem = $item['produit']->getPrice() * $item['quantity'];
            $total += $totalItem;
        }
        
        return $this->render("panier/show_panier.html.twig", [
            'totalCommande' => $total,
        ]);
    }

    /**
     * @Route("/ajouter-un-produit/{id}", name="add", methods={"GET"})
     */
    public function add(Produit $produit, SessionInterface $session): Response
    {
        // Si dans la session le panier n'existe pas, alors la méthode get retournera le second paramètre : un array vide
        $panier = $session->get('panier', []);
        // [id_produit : ['quantity': 1, 'produit': $produit]]
        if ( !empty( $panier[$produit->getId()] ) ){
            ++$panier[$produit->getId()]['quantity'];
        }else{
            $panier[$produit->getId()]['quantity'] = 1;
            $panier[$produit->getId()]['produit'] = $produit;
        }
        // Nous devons mettre à jour la clé panier dans la session en passant le $panier
        $session->set('panier', $panier);
        $this->addFlash('success', 'Le produit ' . $produit->getTitle(). ' a été ajouté à votre panier');
        return $this->redirectToRoute('default_home');
    }

    /**
     * @Route("/vider-le-panier", name="empty_panier", methods={"GET"})
     */
    public function empty(SessionInterface $session): Response
    {
        $session->remove('panier');
        $this->addFlash('success', 'Le panier a été vidé.');
        return $this->redirectToRoute('show_panier');
    }

    /**
     * @Route("/retirer-du-panier/{id}", name="panier_remove", methods={"GET"})
     */
    public function delete(int $id, Produit $produit, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        if (array_key_exists($id, $panier)){
            unset($panier[$id]);
            $session->set('panier', $panier);
            $this->addFlash('success', 'Le produit '. $produit->getTitle() .' a été retiré du panier.');
        }else{
            $this->addFlash('warning', 'Le produit '. $produit->getTitle() .' n\'est pas dans votre panier.');
        }
        return $this->redirectToRoute('show_panier');
    }

    /**
     * @Route("/valider-le-panier", name="panier_validate", methods={"GET"})
     */
    public function validate(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $panier = $session->get('panier', []);
        if ( empty($panier) ){
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('show_panier');
        }else{
            $id_user = $this->getUser();
            
            if ($id_user != null){

                $commande = new Commande();
                // this->getUser() = c'est une variable global qui vérifie si quelqu'un est connecté
                
                $user = $entityManager->getRepository(User::class)->find($id_user);

                $commande->setCreatedAt(new DateTime());
                $commande->setUpdatedAt(new DateTime());
                $commande->setState('en cours');
                $commande->setUser($user);
                $total = 0;
    
                foreach ($panier as $item){
                    $totalItem = $item['produit']->getPrice() * $item['quantity'];
                    $total += $totalItem;
                    //$totalQuantity += $item['quantity'];
                    $commande->addProduct($item['produit']);
                }
    
                $commande->setTotal($total);
                $commande->setQuantity(count($panier));
                $entityManager->persist($commande);
                $entityManager->flush();

                $session->remove('panier');

                $this->addFlash('success', 'Félicitations, votre commande est en cours.\nVous pouvez la retrouver dans Mes Commandes');
                return $this->redirectToRoute('show_account');
            }else{
                // non connecté
                $this->addFlash('warning', 'Connectez-vous pour passer la commande.');
                return $this->redirectToRoute('app_login');
            }
            
        }
        return $this->redirectToRoute('show_panier');
    }

}