<?php
namespace api;
use modele\DAO;
use DOMDocument;
use modele\Outils;
use modele\Utilisateur;
$dao = new DAO();

$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

if ($lang != "json") $lang = "xml";

// La methode HTTP utilisee doit être GET
if ($this->getMethodeRequete() != "GET")
{	$msg = "Erreur : methode HTTP incorrecte.";
$code_reponse = 406;
}

else {
    // Les parametres doivent être presents
    if ($pseudo == '' ) {
        $msg = "Erreur : donnees incompletes ou incorrectes.";
        $code_reponse = 400;
    }
    else{
        if( $dao->existePseudoUtilisateur($pseudo)) {
            // creation d'un mot de passe aleatoire de 8 caracteres
            $password = Outils::creerMdp();
            //$unUtilisateur = new Utilisateur(0, $pseudo, $password, $adrMail, 0, 0, 0, 0, 0);
            
            if ( $password == "" ) {
                $msg = "Erreur : probleme lors de l'enregistrement du mot de passe.";
                $code_reponse = 500;
            }
            else {
                $sujet = "Votre nouveau mot de passe TraceGPS";
                $contenuMail = "Vous venez de vous creer un nouveau mot de passe.\n\n";
                $contenuMail .= "Les donnees enregistrees sont :\n\n";
                $contenuMail .= "Votre pseudo : " . $pseudo . "\n";
                $contenuMail .= "Votre mot de passe : " . $password . "\n";
                $adrMailEmetteur = $dao->getUnUtilisateur($pseudo)->getAdrMail();
                
                $ok = Outils::envoyerMail($adrMailEmetteur, $sujet, $contenuMail, $adrMailEmetteur);
                
                if ( ! $ok ) {
                    // l'envoi de mail a echoue
                    $msg = "Enregistrement effectue ; l'envoi du courriel a l'utilisateur a rencontre un probleme.";
                    $code_reponse = 500;
                }
                else {
                    // tout a bien fonctionne
                    $msg = "Enregistrement effectue ; vous allez recevoir un courriel avec votre mot de passe.";
                    $code_reponse = 201;
                }
            }
        }
        else{
            $msg = "Erreur : pseudo inexistant.";
            $code_reponse = 400;
        }
    }
    }
        
    
    
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
        // place ce commentaire a la racine du document XML
        $doc->appendChild($elt_commentaire);
        
        // cree l'element 'data' a la racine du document XML
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
    

