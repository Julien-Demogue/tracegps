<?php
/*
    Rôle : ce service web permet à un utilisateur de demander une autorisation à un autre utilisateur.
    Parametres à fournir :
    • pseudo : le pseudo de l'utilisateur qui demande l'autorisation
    • mdp : le mot de passe hashe en sha1 de l'utilisateur qui demande l'autorisation
    • pseudoDestinataire : le pseudo de l'utilisateur à qui on demande l'autorisation
    • texteMessage : le texte d'un message accompagnant la demande
    • nomPrenom : le nom et le prenom du demandeur
    • lang : le langage utilise pour le flux de donnees ("xml" ou "json")
    
    Description du traitement :
    • Verifier que les donnees transmises sont completes
    • Verifier l'authentification de l'utilisateur demandeur
    • Verifier que le pseudo de l'utilisateur destinataire existe
    • Envoyer un courriel à l'utilisateur destinataire
*/

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
$pseudoDestinataire = ( empty($this->request['pseudoDestinataire'])) ? "" : $this->request['pseudoDestinataire'];
$nomPrenom = ( empty($this->request['nomPrenom'])) ? "" : $this->request['nomPrenom'];
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
    }
    else {
        // Verification l'authentification de l'utilisateur demandeur
        $utilisateur = $dao->getUnUtilisateur($pseudo);
        if($utilisateur == null || $utilisateur->getPseudo() != $pseudo || $utilisateur->getMdpSha1() != $mdp || $dao->getNiveauConnexion($pseudo, $mdp) == 0 ){
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }        
        else {
            // Verification que le pseudo de l'utilisateur destinataire existe
            if  ($dao->existePseudoUtilisateur($pseudoDestinataire) != true) {
                $msg = 'Erreur : pseudo utilisateur inexistant';
                $code_reponse = 402;
            }
            else {
                // Envoi du courriel à l'utilisateur destinataire
                $adresseEmetteur = $utilisateur->getAdrMail();
                $adresseDestinataire = $dao->getUnUtilisateur($pseudoDestinataire)->getAdrMail();
                
                $sujet = "Demande d'autorisation de la part d'un utilisateur du systeme TraceGPS";
                
                $message = "Cher ou chere " . $pseudoDestinataire . 
                "\n Un utilisateur du systeme TraceGPS vous demande l'autorisation de suivre vos parcours. 
                 \n Voici les donnees le concernant : \n\n Son pseudo : " . $utilisateur->getPseudo() . 
                "\n Son adresse mail : " . $adresseEmetteur .
                "\n Son numero de telephone : " . $utilisateur->getNumTel() .
                "\n Son nom et prénom : " . $nomPrenom .
                "\n Son message : " . $texteMessage .
                "\n\n Pour accepter la demande, cliquez sur ce lien :\n http://localhost/ws-php-filippi/tracegps/api/services/ValiderDemandeAutorisation.php?a=".$mdp."&b=".$pseudoDestinataire."&c=".$pseudo."&d=1 
                 \n\n Pour rejeter la demande, cliquez sur ce lien :\n http://localhost/ws-php-filippi/tracegps/api/services/ValiderDemandeAutorisation.php?a=".$mdp."&b=".$pseudoDestinataire."&c=".$pseudo."&d=0";                                 
                
                $ok = Outils::envoyerMail($adresseDestinataire,$sujet,$message,$adresseEmetteur);
                if (! $ok){
                    $msg = "Erreur : l'envoi du courriel de demande d\'autorisation a rencontre un probleme";
                    $code_reponse = 500;
                }else{
                    $msg = $pseudoDestinataire . " va recevoir un courriel avec votre demande.";
                    $code_reponse = 200;
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

