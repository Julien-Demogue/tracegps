<?php

// Rôle : ce service web permet à un utilisateur de supprimer une autorisation qu'il avait accordée à un
// autre utilisateur
//
// Paramètres à fournir :
// • pseudo : le pseudo de l'utilisateur qui retire l'autorisation
// • mdp : le mot de passe hashé en sha1 de l'utilisateur qui retire l'autorisation
// • pseudoARetirer : le pseudo de l'utilisateur à qui on veut retirer l'autorisation
// • texteMessage : le texte d'un message accompagnant la suppression
// • lang : le langage utilisé pour le flux de données ("xml" ou "json")
// 
// Description du traitement :
// • Vérifier que les données transmises sont complètes
// • Vérifier l'authentification de l'utilisateur qui veut supprimer une autorisation
// • Vérifier l'existence du pseudo de l'utilisateur à qui on désire supprimer l'autorisation
// • Vérifier que l'autorisation à retirer était bien accordée
// • Supprimer l'autorisation dans la base de données
// • Envoyer un courriel à l'utilisateur à qui on a supprimé l'autorisation (uniquement si le texte du
//     message n'est pas vide)






namespace api;
use modele\DAO;
use modele\Utilisateur;
use modele\Outils;
use DOMDocument;

// Connexion au serveur et à la base de donnees
$dao = new DAO();

// Recuperation des donnees transmises (recuperation des parametres)
$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdp = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$pseudoARetirer = ( empty($this->request['pseudoARetirer'])) ? "" : $this->request['pseudoARetirer'];
$texteMessage = (empty($this->request['texteMessage'])) ? "" : $this->request['texteMessage'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

// "xml" par defaut si le parametre lang est absent ou incorrect
if ($lang != "json") $lang = "xml";

// La methode HTTP utilisee doit être GET
if ($this->getMethodeRequete() != "GET")
{	$msg = "Erreur : methode HTTP incorrecte.";
$code_reponse = 406;
}
else {
    // Verification que les donnees transmises sont completes
    if ( $pseudo == "" || $mdp == "" || $pseudoDestinataire == "" || $lang == "" ) {
        $msg = "Erreur : donnees incompletes.";
        $code_reponse = 400;
    }else{
        
    }
}

    
    
    
    
    
    
    
    
    
    
    
    
    
    ?>