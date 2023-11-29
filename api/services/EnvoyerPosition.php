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
use modele\PointDeTrace;
use DOMDocument;

// Connexion au serveur et à la base de donnees
$dao = new DAO();

// Recuperation des donnees transmises (recuperation des parametres)
$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdp = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$idTrace = ( empty($this->request['idTrace'])) ? "" : $this->request['idTrace'];
$dateHeure = ( empty($this->request['dateHeure'])) ? "" : $this->request['dateHeure'];
$latitude = ( empty($this->request['latitude'])) ? "" : $this->request['latitude'];
$longitude = ( empty($this->request['longitude'])) ? "" : $this->request['longitude'];
$altitude = ( empty($this->request['altitude'])) ? "" : $this->request['altitude'];
$rythmeCardio = ( empty($this->request['rythmeCardio'])) ? "" : $this->request['rythmeCardio'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

$idNouveauPDT = null;

// "xml" par defaut si le parametre lang est absent ou incorrect
if ($lang != "json") $lang = "xml";

// La methode HTTP utilisee doit être GET
if ($this->getMethodeRequete() != "GET")
{	$msg = "Erreur : methode HTTP incorrecte.";
$code_reponse = 406;
}
else {
    // Verification que les donnees transmises sont completes
    if ( $pseudo == "" || $mdp == "" || $idTrace == "" || $dateHeure == "" || $latitude == "" || $longitude == "" || $altitude == "" || $rythmeCardio == "" ||  $lang == "" ) {
        $msg = "Erreur : donnees incompletes.";
        $code_reponse = 400;
    }else{
        // Verification l'authentification de l'utilisateur
        $utilisateur = $dao->getUnUtilisateur($pseudo);
        if($utilisateur == null || $utilisateur->getPseudo() != $pseudo || $utilisateur->getMdpSha1() != $mdp || $dao->getNiveauConnexion($pseudo, $mdp) == 0 ){
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }else{
            //Vérification de l'existence du numero de trace
            if  ($dao->getUneTrace($idTrace) == null) {
                $msg = 'Erreur : le numero de trace n\'existe pas';
                $code_reponse = 402;
            }else{
                $ok = false;
                $lesTraces = $dao->getLesTraces($dao->getUnUtilisateur($pseudo)->getId());
                foreach($lesTraces as $uneTrace){
                    if($idTrace == $uneTrace->getId()){$ok = true;}
                }
                
                // Vérification du resultat de l'operation
                if  (!$ok) {
                    
                    $msg = "Erreur : le numero de trace ne correspond pas a cet utilisateur.";
                    $code_reponse = 403;
                }else{
                    if($dao->getUneTrace($idTrace)->getTerminee() == 1){
                        $msg = "Erreur : la trace est deja terminee.";
                        $code_reponse = 400;
                    }else{
                        $nouveauPDT = new PointDeTrace($idTrace,0,$latitude,$longitude,$altitude,$dateHeure,$rythmeCardio,0,0,0);
                        $ok = $dao->creerUnPointDeTrace($nouveauPDT);
                        if(!$ok){
                            $msg = "Erreur : probleme lors de l\'enregistrement du point.";
                            $code_reponse = 400;
                        }else{
                            $lesPointsDeTrace = $dao->getLesPointsDeTrace($idTrace);
                            $idNouveauPDT = end($lesPointsDeTrace)->getId();
                            $msg = "Point cree.";
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
    $donnees = creerFluxXML ($msg,$idNouveauPDT);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la reponse
    $donnees = creerFluxJSON ($msg,$idNouveauPDT);
}

// envoi de la reponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// creation du flux XML en sortie
function creerFluxXML($msg,$idNouveauPDT)
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
    
    // place l'element 'donnees' juste apres l'element 'data'
    $elt_donnees = $doc->createElement('donnees');
    $elt_data->appendChild($elt_donnees);
    
    if($idNouveauPDT != null){
        // place l'element 'id' juste apres l'element 'data'
        $elt_donnees = $doc->createElement('id', $idNouveauPDT);
        $elt_data->appendChild($elt_donnees);
    }    
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// creation du flux JSON en sortie
function creerFluxJSON($msg,$idNouveauPDT)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    if($idNouveauPDT == null){
        $elt_data = ["reponse" => $msg, "donnees"=> "[]"];
    }else{
        // construction de l'element "data"
        $elt_donnees = ["id" => $idNouveauPDT];
        $elt_data = ["reponse" => $msg, "donnees"=> $elt_donnees];
    }
    
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gere les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================


?>