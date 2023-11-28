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
    if ( $pseudo == "" || $mdp == "" || $pseudoARetirer == "" || $lang == "" ) {
        $msg = "Erreur : donnees incompletes.";
        $code_reponse = 400;
    }else{
        // Verification l'authentification de l'utilisateur demandeur
        $utilisateur = $dao->getUnUtilisateur($pseudo);
        if($utilisateur == null || $utilisateur->getPseudo() != $pseudo || $utilisateur->getMdpSha1() != $mdp || $dao->getNiveauConnexion($pseudo, $mdp) == 0 ){
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }else{
            //Vérification de l'existence du pseudo de l'utilisateur à qui on désire supprimer l'autorisation
            if  ($dao->existePseudoUtilisateur($pseudoARetirer) != true) {
                $msg = 'Erreur : pseudo utilisateur inexistant';
                $code_reponse = 402;
            }else{
                //Vérification que l'autorisation à retirer était bien accordée
                $ok = false;
                $utilisateursAutorises = $dao->getLesUtilisateursAutorises($dao->getUnUtilisateur($pseudo)->getId());
                foreach($utilisateursAutorises as $utilisateur){
                    if($utilisateur->getPseudo() == $pseudoARetirer){ $ok = true;}
                }
                // Vérification du resultat de l'operation
                if  (!$ok) {
                    $msg = "Erreur : l'autorisation n'était pas accordée.";
                    $code_reponse = 403;
                }else{
                    // Suppression de l'autorisation dans la base de données
                    $dao->supprimerUneAutorisation($dao->getUnUtilisateur($pseudo)->getId(), $dao->getUnUtilisateur($pseudoARetirer)->getId());
                    
                    if($texteMessage ==""){
                        $msg = "Autorisation supprimee.";
                        $code_reponse = 200;
                    }else{
                        // Envoi du courriel à l'utilisateur destinataire
                        $adresseDestinataire = $dao->getUnUtilisateur($pseudoARetirer)->getAdrMail();
                        $sujet = "Suppression d'autorisation de la part d'un utilisateur du systeme TraceGPS";
                        $message = "Cher ou chere " . $pseudoARetirer .
                        "\n\nL\'utilisateur " . $pseudo . " du systeme TraceGPS vous retire l'autorisation de suivre ses parcours.\n".
                        "\n Son message : " . $texteMessage .
                        "\n\nCordialement
                         \nL\'administrateur du système TraceGPS";
                        $adresseEmetteur = $utilisateur->getAdrMail();
                        
                        $ok = Outils::envoyerMail($adresseDestinataire,$sujet,$message,$adresseEmetteur);
                        if (! $ok){
                            $msg = "Erreur : l'envoi du courriel de demande d\'autorisation a rencontre un probleme";
                            $code_reponse = 500;
                        }else{
                            $msg = $pseudoARetirer . " va recevoir un courriel avec votre demande.";
                            $code_reponse = 200;
                        }
                    }
                }
            }
        }
    }
}
// ferme la connexion à MySQL :
unset($dao);

// creation du flux en sortie
if ($lang == "xml") {
    $content_type = "application/xml; charset=utf-8";      // indique le format XML pour la reponse
    $donnees = creerFluxXML ($msg);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la reponse
    $donnees = creerFluxJSON ($msg);
}

// envoi de la reponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// creation du flux XML en sortie
function creerFluxXML($msg)
{
    /* Exemple de code XML
     <?xml version="1.0" encoding="UTF-8"?>
     <!--Service web ChangerDeMdp - BTS SIO - Lycee De La Salle - Rennes-->
     <data>
     <reponse>Erreur : authentification incorrecte.</reponse>
     </data>
     */
    
    // cree une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // cree un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web ChangerDeMdp - BTS SIO - Lycee De La Salle - Rennes');
    // place ce commentaire à la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // cree l'element 'data' à la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'element 'reponse' juste apres l'element 'data'
    $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// creation du flux JSON en sortie
function creerFluxJSON($msg)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    
    // construction de l'element "data"
    $elt_data = ["reponse" => $msg];
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gere les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================
    
    
?>