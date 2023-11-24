<?php
// Projet TraceGPS - services web
// fichier :  api/services/CreerUnUtilisateur.php
// Derniere mise à jour : 21/11/2023 par Julien

// Rôle : ce service permet à un utilisateur de supprimer un de ses parcours (ou traces).
// Le service web doit recevoir 4 parametres :
//      pseudo : le pseudo de l'utilisateur qui demande à supprimer
//      mdp : le mot de passe hashé en sha1 de l'utilisateur qui demande à supprimer
//      idTrace : l'id de la trace à supprimer
//      lang : le langage utilisé pour le flux de donnees ("xml" ou "json")
//  Le service retourne un flux de donnees XML ou JSON contenant un compte-rendu d'execution
namespace api;
use modele\DAO;
use DOMDocument;
// connexion du serveur web à la base MySQL
$dao = new DAO();

// Recuperation des donnees transmises
$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdp = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$idTrace = ( empty($this->request['idTrace'])) ? "" : $this->request['idTrace'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

// "xml" par defaut si le parametre lang est absent ou incorrect
if ($lang != "json") $lang = "xml";

// La methode HTTP utilisee doit etre GET
if ($this->getMethodeRequete() != "GET"){	
    $msg = "Erreur : methode HTTP incorrecte.";
    $code_reponse = 406;
}
else {
    // Les paramètres doivent être présents
    if ( $pseudo == "" || $mdp == "" || $idTrace == "" ){	
        $msg = "Erreur : donnees incompletes.";
        $code_reponse = 400;
    }
    else
    {	// il faut être utilisateur pour supprimer un parcours
        if ( $dao->getNiveauConnexion($pseudo, $mdp) != 1 ){   
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }
        else
        {	// contrôle d'existence de idTrace
            $uneTrace = $dao->getUneTrace($idTrace);
            if ($uneTrace == null){  
                $msg = "Erreur : parcours inexistant.";
                $code_reponse = 400;
            }
            else
            {   
                //Verification que la trace appartient a l'utilisateur
                
                if($uneTrace->getIdUtilisateur() != $dao->getUnUtilisateur($pseudo)->getId()){
                    $msg = "Erreur : vous n'etes pas le proprietaire de ce parcours.";
                    $code_reponse = 401;
                }
                else{
                    // suppression de l'utilisateur dans la BDD
                    $ok = $dao->supprimerUneTrace($idTrace);
                    if ( !$ok ) {
                        $msg = "Erreur : probleme lors de la suppression du parcours.";
                        $code_reponse = 500;
                    }
                    else {
                        // tout a fonctionné
                        $msg = "Parcours supprime";
                        $code_reponse = 200;
                    }
                }
            }
            
        }
        
    }
}

// ferme la connexion à MySQL :
unset($dao);

// création du flux en sortie
if ($lang == "xml") {
    $content_type = "application/xml; charset=utf-8";      // indique le format XML pour la réponse
    $donnees = creerFluxXML($msg);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la réponse
    $donnees = creerFluxJSON($msg);
}

// envoi de la réponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// création du flux XML en sortie
function creerFluxXML($msg)
{	// crée une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // crée un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web SupprimerUnParcours - BTS SIO - Lycée De La Salle - Rennes');
    // place ce commentaire à la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // crée l'élément 'data' à la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'élément 'reponse' dans l'élément 'data'
    $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// création du flux JSON en sortie
function creerFluxJSON($msg)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    
    // construction de l'élément "data"
    $elt_data = ["reponse" => $msg];
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gère les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================

?>