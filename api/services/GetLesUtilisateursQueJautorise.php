<?php
namespace api;
use modele\DAO;
use DOMDocument;
// connexion du serveur web à la base MySQL
$dao = new DAO();

$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdp = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];
$utilisateursAutorises = array();

if ($lang != "json") $lang = "xml";

// La methode HTTP utilisee doit etre GET
if ($this->getMethodeRequete() != "GET"){
    $msg = "Erreur : methode HTTP incorrecte.";
    $code_reponse = 406;
}
else{
    // Les paramètres doivent être présents
    if ( $pseudo == "" || $mdp == ""){
        $msg = "Erreur : donnees incompletes.";
        $code_reponse = 400;
    }
    else {
        // il faut être utilisateur pour recuperer les utilisateurs qui autorisent
        if ( $dao->getNiveauConnexion($pseudo, $mdp) != 1 ){
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }
        else{
            // Recuperation des utilisateurs autorise
            $unUtilisateur = $dao->getUnUtilisateur($pseudo);
            $idUtilisateur = $unUtilisateur->getId();
            $utilisateursAutorises = $dao->getLesUtilisateursAutorises($idUtilisateur);
            
            $nbAutorisations = sizeof($utilisateursAutorises);
            if($nbAutorisations == 0){
                $msg = "Aucune autorisation accordee par ".$pseudo;
                $code_reponse = 200;
            }
            else{
                $msg = $nbAutorisations ." autorisation(s) accordee(s) par " . $pseudo;
                $code_reponse = 200;
            }
        }
        
    }
}

unset($dao);

// création du flux en sortie
if ($lang == "xml") {
    $content_type = "application/xml; charset=utf-8";      // indique le format XML pour la réponse
    $donnees = creerFluxXML ($msg,$utilisateursAutorises);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la réponse
    $donnees = creerFluxJSON ($msg,$utilisateursAutorises);
}

// envoi de la réponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// création du flux XML en sortie
function creerFluxXML($msg,$utilisateurs)
{	// crée une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // crée un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web GetLesUtilisateursQueJautorise - BTS SIO - Lycée De La Salle - Rennes');
    // place ce commentaire à la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // crée l'élément 'data' à la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'élément 'reponse' dans l'élément 'data'
    $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    //Renseigner les champs de l'utilisateur
    $elt_utilisateurs = $doc->createElement('lesUtilisateurs');
    foreach ($utilisateurs as $utilisateur) {
        $elt_utilisateur = $doc->createElement('utilisateur');
        $elt_id = $doc->createElement('id',$utilisateur->getId()); $elt_utilisateur->appendChild($elt_id);
        $elt_pseudo = $doc->createElement('pseudo',$utilisateur->getPseudo()); $elt_utilisateur->appendChild($elt_pseudo);
        $elt_adrMail = $doc->createElement('adrMail',$utilisateur->getAdrMail()); $elt_utilisateur->appendChild($elt_adrMail);
        $elt_numTel = $doc->createElement('numTel',$utilisateur->getNumTel()); $elt_utilisateur->appendChild($elt_numTel);
        $elt_niveau = $doc->createElement('niveau',$utilisateur->getNiveau()); $elt_utilisateur->appendChild($elt_niveau);
        $elt_dateCreation = $doc->createElement('dateCreation',$utilisateur->getDateCreation()); $elt_utilisateur->appendChild($elt_dateCreation);
        
        if($utilisateur->getNbTraces() > 0){
            $elt_nbTraces = $doc->createElement('nbTraces',$utilisateur->getNbTraces()); $elt_utilisateur->appendChild($elt_nbTraces);
            $elt_dateDernieretrace = $doc->createElement('dateDerniereTrace',$utilisateur->getDateDernieretrace()); $elt_utilisateur->appendChild($elt_dateDernieretrace);
        }
        
        $elt_utilisateurs->appendChild($elt_utilisateur);
    }
    
    $elt_donnees = $doc->createElement('donnees');
    $elt_donnees->appendChild($elt_utilisateurs);
    
    $elt_data->appendChild($elt_donnees);
    
    //$elt_utilisateurs = ["lesUtilisateurs" => $elt_lesParamUtilisateurs];
    
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// création du flux JSON en sortie
function creerFluxJSON($msg,$utilisateurs)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    
    //Renseigner les champs de l'utilisateur
    $elt_lesParamUtilisateurs = [];
    foreach ($utilisateurs as $utilisateur) {
        $elt_paramUtilisateur = [
            "id" => $utilisateur->getId(),
            "pseudo" => $utilisateur->getPseudo(),
            "adrMail" => $utilisateur->getAdrMail(),
            "numTel" => $utilisateur->getNumTel(),
            "niveau" => $utilisateur->getNiveau(),
            "dateCreation" => $utilisateur->getDateCreation(),
        ];
        
        if($utilisateur->getNbTraces() > 0){
            $elt_paramUtilisateur += ["nbTraces" => $utilisateur->getNbTraces(),
                "dateDerniereTrace" => $utilisateur->getDateDerniereTrace()];
        }
        
        $elt_lesParamUtilisateurs[] = $elt_paramUtilisateur;
    }
    
    $elt_utilisateurs = ["lesUtilisateurs" => $elt_lesParamUtilisateurs];
    
    // construction de l'élément "data"
    $elt_data = ["reponse" => $msg, "donnees" =>$elt_utilisateurs];
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gère les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================
?>
