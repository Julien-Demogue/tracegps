<?php
// Projet TraceGPS - services web
// fichier :  api/services/DemarrerEnregistrementParcours.php
// Derniere mise à jour : 24/11/2023 par Julien

// Rôle : ce service web permet à un utilisateur de démarrer l'enregistrement d'un parcours.
// Le service web doit recevoir 3 parametres :
//      pseudo : le pseudo de l'utilisateur
//      mdp : le mot de passe de l'utilisateur hashé en sha1
//      lang : le langage utilisé pour le flux de données ("xml" ou "json")
//  Le service retourne un flux de donnees XML ou JSON contenant un compte-rendu d'execution
namespace api;
use modele\DAO;
use modele\Trace;
use DOMDocument;
// connexion du serveur web à la base MySQL
$dao = new DAO();

$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdp = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

// "xml" par defaut si le parametre lang est absent ou incorrect
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
    else{
        // il faut être utilisateur pour demarrer un enregistrement
        if ( $dao->getNiveauConnexion($pseudo, $mdp) != 1 ){
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }
        else{
            // Creer une nouvelle Trace
            $uneDateHeureDebut = date('Y-m-d H:i:s');
            $unIdUtilisateur = $dao->getUnUtilisateur($pseudo)->getId();
            $uneTrace = new Trace(0,$uneDateHeureDebut,null,0,$unIdUtilisateur);
            $ok = $dao->creerUneTrace($uneTrace);
            
            if(!$ok){
                $msg = "Erreur : problème lors de l'enregistrement.";
                $code_reponse = 500;
            }
            else{
                $msg = "Trace creee.";
                $code_reponse = 200;
            }
        }
    }
}

// ferme la connexion à MySQL :
unset($dao);

// création du flux en sortie
if ($lang == "xml") {
    $content_type = "application/xml; charset=utf-8";      // indique le format XML pour la réponse
    $donnees = creerFluxXML ($msg,$uneTrace);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la réponse
    $donnees = creerFluxJSON ($msg,$uneTrace);
}

// envoi de la réponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// création du flux XML en sortie
function creerFluxXML($msg,$trace)
{	// crée une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // crée un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web DemarrerEnregistrementparcours - BTS SIO - Lycée De La Salle - Rennes');
    // place ce commentaire à la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // crée l'élément 'data' à la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'élément 'reponse' dans l'élément 'data'
    $elt_reponse = $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    // ajouter les parametres de la trace
    $elt_trace = $doc->createElement('trace');
    $elt_id = $doc->createElement('id',$trace->getId());                                  $elt_trace->appendChild($elt_id);
    $elt_dateHDebut = $doc->createElement('dateHeureDebut',$trace->getDateHeureDebut());  $elt_trace->appendChild($elt_dateHDebut);
    $elt_terminee = $doc->createElement('terminee',$trace->getTerminee());                $elt_trace->appendChild($elt_terminee);
    $elt_idUtilisateur = $doc->createElement('idUtilisateur',$trace->getIdUtilisateur()); $elt_trace->appendChild($elt_idUtilisateur);
    
    // place l'element 'donnees' dans l'element 'data'
    $elt_donnees = $doc->createElement('donnees');
    $elt_donnees->appendChild($elt_trace);
    $elt_data->appendChild($elt_donnees);
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// création du flux JSON en sortie
function creerFluxJSON($msg,$trace)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    
    // construction des donnees de la trace
    $elt_trace = ["trace" => [
        "id" => $trace->getId(),
        "dateHeureDebut" => $trace->getDateHeureDebut(),
        "terminee" => $trace->getTerminee(),
        "idUtilisateur" => $trace->getIdUtilisateur()
        ]
    ];
    
    // construction de l'élément "data" et "donnees"
    $elt_data = ["reponse" => $msg, "donnees" => $elt_trace];
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gère les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================
?>