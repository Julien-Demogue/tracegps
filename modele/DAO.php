<?php
namespace modele;

use Exception;
// Projet TraceGPS
// fichier : modele/DAO.class.php   (DAO : Data Access Object)
// Rôle : fournit des méthodes d'accès à la bdd tracegps (projet TraceGPS) au moyen de l'objet \PDO
// modifié par dP le 12/8/2021

// liste des méthodes déjà développées (dans l'ordre d'apparition dans le fichier) :

// __construct() : le constructeur crée la connexion $cnx à la base de données
// __destruct() : le destructeur ferme la connexion $cnx à la base de données
// getNiveauConnexion($login, $mdp) : fournit le niveau (0, 1 ou 2) d'un utilisateur identifié par $login et $mdp
// existePseudoUtilisateur($pseudo) : fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
// getUnUtilisateur($login) : fournit un objet Utilisateur à partir de $login (son pseudo ou son adresse mail)
// getTousLesUtilisateurs() : fournit la collection de tous les utilisateurs (de niveau 1)
// creerUnUtilisateur($unUtilisateur) : enregistre l'utilisateur $unUtilisateur dans la bdd
// modifierMdpUtilisateur($login, $nouveauMdp) : enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $login daprès l'avoir hashé en SHA1
// supprimerUnUtilisateur($login) : supprime l'utilisateur $login (son pseudo ou son adresse mail) dans la bdd, ainsi que ses traces et ses autorisations
// envoyerMdp($login, $nouveauMdp) : envoie un mail à l'utilisateur $login avec son nouveau mot de passe $nouveauMdp

// liste des méthodes restant à développer :

// existeAdrMailUtilisateur($adrmail) : fournit true si l'adresse mail $adrMail existe dans la table tracegps_utilisateurs, false sinon
// getLesUtilisateursAutorises($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisés à suivre l'utilisateur $idUtilisateur
// getLesUtilisateursAutorisant($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisant l'utilisateur $idUtilisateur à voir leurs parcours
// autoriseAConsulter($idAutorisant, $idAutorise) : vérifie que l'utilisateur $idAutorisant) autorise l'utilisateur $idAutorise à consulter ses traces
// creerUneAutorisation($idAutorisant, $idAutorise) : enregistre l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// supprimerUneAutorisation($idAutorisant, $idAutorise) : supprime l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// getLesPointsDeTrace($idTrace) : fournit la collection des points de la trace $idTrace
// getUneTrace($idTrace) : fournit un objet Trace à partir de identifiant $idTrace
// getToutesLesTraces() : fournit la collection de toutes les traces
// getLesTraces($idUtilisateur) : fournit la collection des traces de l'utilisateur $idUtilisateur
// getLesTracesAutorisees($idUtilisateur) : fournit la collection des traces que l'utilisateur $idUtilisateur a le droit de consulter
// creerUneTrace(Trace $uneTrace) : enregistre la trace $uneTrace dans la bdd
// terminerUneTrace($idTrace) : enregistre la fin de la trace d'identifiant $idTrace dans la bdd ainsi que la date de fin
// supprimerUneTrace($idTrace) : supprime la trace d'identifiant $idTrace dans la bdd, ainsi que tous ses points
// creerUnPointDeTrace(PointDeTrace $unPointDeTrace) : enregistre le point $unPointDeTrace dans la bdd


// certaines méthodes nécessitent les classes suivantes :
include_once ('Utilisateur.php');
include_once ('Trace.php');
include_once ('PointDeTrace.php');
include_once ('Point.php');
include_once ('Outils.php');

// inclusion des paramètres de l'application
include_once ('parametres.php');

// début de la classe DAO (Data Access Object)
class DAO
{
    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Membres privés de la classe ---------------------------------------
    // ------------------------------------------------------------------------------------------------------
    
    private $cnx;				// la connexion à la base de données
    
    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Constructeur et destructeur ---------------------------------------
    // ------------------------------------------------------------------------------------------------------
    public function __construct() {
        global $PARAM_HOTE, $PARAM_PORT, $PARAM_BDD, $PARAM_USER, $PARAM_PWD;
        try
        {	$this->cnx = new \PDO("mysql:host=" . $PARAM_HOTE . ";port=" . $PARAM_PORT . ";dbname=" . $PARAM_BDD,
            $PARAM_USER,
            $PARAM_PWD);
        return true;
        }
        catch (Exception $ex)
        {	echo ("Echec de la connexion a la base de donnees <br>");
        echo ("Erreur numero : " . $ex->getCode() . "<br />" . "Description : " . $ex->getMessage() . "<br>");
        echo ("PARAM_HOTE = " . $PARAM_HOTE);
        return false;
        }
    }
    
    public function __destruct() {
        // ferme la connexion à MySQL :
        unset($this->cnx);
    }
    
    // ------------------------------------------------------------------------------------------------------
    // -------------------------------------- Méthodes d'instances ------------------------------------------
    // ------------------------------------------------------------------------------------------------------
    
    // fournit le niveau (0, 1 ou 2) d'un utilisateur identifié par $pseudo et $mdpSha1
    // cette fonction renvoie un entier :
    //     0 : authentification incorrecte
    //     1 : authentification correcte d'un utilisateur (pratiquant ou personne autorisée)
    //     2 : authentification correcte d'un administrateur
    // modifié par dP le 11/1/2018
    public function getNiveauConnexion($pseudo, $mdpSha1) {
        // préparation de la requête de recherche
        $txt_req = "Select niveau from tracegps_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $txt_req .= " and mdpSha1 = :mdpSha1";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, \PDO::PARAM_STR);
        $req->bindValue("mdpSha1", $mdpSha1, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // traitement de la réponse
        $reponse = 0;
        if ($uneLigne) {
            $reponse = $uneLigne->niveau;
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la réponse
        return $reponse;
    }
    
    
    // fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
    // modifié par dP le 27/12/2017
    public function existePseudoUtilisateur($pseudo) {
        // préparation de la requête de recherche
        $txt_req = "Select count(*) from tracegps_utilisateurs where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, \PDO::PARAM_STR);
        // exécution de la requête
        $req->execute();
        $nbReponses = $req->fetchColumn(0);
        // libère les ressources du jeu de données
        $req->closeCursor();
        
        // fourniture de la réponse
        if ($nbReponses == 0) {
            return false;
        }
        else {
            return true;
        }
    }
    
    
    // fournit un objet Utilisateur à partir de son pseudo $pseudo
    // fournit la valeur null si le pseudo n'existe pas
    // modifié par dP le 9/1/2018
    public function getUnUtilisateur($pseudo) {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // libère les ressources du jeu de données
        $req->closeCursor();
        
        // traitement de la réponse
        if ( ! $uneLigne) {
            return null;
        }
        else {
            // création d'un objet Utilisateur
            $unId = mb_convert_encoding($uneLigne->id, "UTF-8");
            $unPseudo = mb_convert_encoding($uneLigne->pseudo, "UTF-8");
            $unMdpSha1 = mb_convert_encoding($uneLigne->mdpSha1, "UTF-8");
            $uneAdrMail = mb_convert_encoding($uneLigne->adrMail, "UTF-8");
            $unNumTel = mb_convert_encoding($uneLigne->numTel, "UTF-8");
            $unNiveau = mb_convert_encoding($uneLigne->niveau, "UTF-8");
            $uneDateCreation = mb_convert_encoding($uneLigne->dateCreation, "UTF-8");
            $unNbTraces = mb_convert_encoding($uneLigne->nbTraces, "UTF-8");
            if (isset($uneLigne->dateDerniereTrace)) {
                $uneDateDerniereTrace = mb_convert_encoding($uneLigne->dateDerniereTrace, "UTF-8");
            } else {
                $uneDateDerniereTrace ="";
            }
            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            return $unUtilisateur;
        }
    }
    
    
    // fournit la collection  de tous les utilisateurs (de niveau 1)
    // le résultat est fourni sous forme d'une collection d'objets Utilisateur
    // modifié par dP le 27/12/2017
    public function getTousLesUtilisateurs() {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where niveau = 1";
        $txt_req .= " order by pseudo";
        
        $req = $this->cnx->prepare($txt_req);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        
        // construction d'une collection d'objets Utilisateur
        $lesUtilisateurs = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
            $unId = mb_convert_encoding($uneLigne->id, "UTF-8");
            $unPseudo = mb_convert_encoding($uneLigne->pseudo, "UTF-8");
            $unMdpSha1 = mb_convert_encoding($uneLigne->mdpSha1, "UTF-8");
            $uneAdrMail = mb_convert_encoding($uneLigne->adrMail, "UTF-8");
            $unNumTel = mb_convert_encoding($uneLigne->numTel, "UTF-8");
            $unNiveau = mb_convert_encoding($uneLigne->niveau, "UTF-8");
            $uneDateCreation = mb_convert_encoding($uneLigne->dateCreation, "UTF-8");
            $unNbTraces = mb_convert_encoding($uneLigne->nbTraces, "UTF-8");
            if (isset($uneLigne->dateDerniereTrace)) {
                $uneDateDerniereTrace = mb_convert_encoding($uneLigne->dateDerniereTrace, "UTF-8");
            } else {
                $uneDateDerniereTrace ="";
            }
            
            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            // ajout de l'utilisateur à la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }
    
    
    // enregistre l'utilisateur $unUtilisateur dans la bdd
    // fournit true si l'enregistrement s'est bien effectué, false sinon
    // met à jour l'objet $unUtilisateur avec l'id (auto_increment) attribué par le SGBD
    // modifié par dP le 9/1/2018
    public function creerUnUtilisateur($unUtilisateur) {
        // on teste si l'utilisateur existe déjà
        if ($this->existePseudoUtilisateur($unUtilisateur->getPseudo())) return false;
        
        // préparation de la requête
        $txt_req1 = "insert into tracegps_utilisateurs (pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation)";
        $txt_req1 .= " values (:pseudo, :mdpSha1, :adrMail, :numTel, :niveau, :dateCreation)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue("pseudo", mb_convert_encoding($unUtilisateur->getPseudo(), "UTF-8"), \PDO::PARAM_STR);
        $req1->bindValue("mdpSha1", mb_convert_encoding(sha1($unUtilisateur->getMdpsha1(), "UTF-8"), "UTF-8"), \PDO::PARAM_STR);
        $req1->bindValue("adrMail", mb_convert_encoding($unUtilisateur->getAdrmail(), "UTF-8"), \PDO::PARAM_STR);
        $req1->bindValue("numTel", mb_convert_encoding($unUtilisateur->getNumTel(), "UTF-8"), \PDO::PARAM_STR);
        $req1->bindValue("niveau", mb_convert_encoding($unUtilisateur->getNiveau(), "UTF-8"), \PDO::PARAM_INT);
        $req1->bindValue("dateCreation", mb_convert_encoding($unUtilisateur->getDateCreation(), "UTF-8"), \PDO::PARAM_STR);
        // exécution de la requête
        $ok = $req1->execute();
        // sortir en cas d'échec
        if ( ! $ok) { return false; }
        
        // recherche de l'identifiant (auto_increment) qui a été attribué à la trace
        $unId = $this->cnx->lastInsertId();
        $unUtilisateur->setId($unId);
        return true;
    }
    
    
    // enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $pseudo daprès l'avoir hashé en SHA1
    // fournit true si la modification s'est bien effectuée, false sinon
    // modifié par dP le 9/1/2018
    public function modifierMdpUtilisateur($pseudo, $nouveauMdp) {
        // préparation de la requête
        $txt_req = "update tracegps_utilisateurs set mdpSha1 = :nouveauMdp";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("nouveauMdp", sha1($nouveauMdp), \PDO::PARAM_STR);
        $req->bindValue("pseudo", $pseudo, \PDO::PARAM_STR);
        // exécution de la requête
        $ok = $req->execute();
        return $ok;
    }
    
    
    // supprime l'utilisateur $pseudo dans la bdd, ainsi que ses traces et ses autorisations
    // fournit true si l'effacement s'est bien effectué, false sinon
    // modifié par dP le 9/1/2018
    public function supprimerUnUtilisateur($pseudo) {
        $unUtilisateur = $this->getUnUtilisateur($pseudo);
        if ($unUtilisateur == null) {
            return false;
        }
        else {
            $idUtilisateur = $unUtilisateur->getId();
            
            // suppression des traces de l'utilisateur (et des points correspondants)
            $lesTraces = $this->getLesTraces($idUtilisateur);
            if($lesTraces != null)
            {
                foreach ($lesTraces as $uneTrace) {
                    $this->supprimerUneTrace($uneTrace->getId());
                }
            }
            // préparation de la requête de suppression des autorisations
            $txt_req1 = "delete from tracegps_autorisations" ;
            $txt_req1 .= " where idAutorisant = :idUtilisateur or idAutorise = :idUtilisateur";
            $req1 = $this->cnx->prepare($txt_req1);
            // liaison de la requête et de ses paramètres
            $req1->bindValue("idUtilisateur", mb_convert_encoding($idUtilisateur, "UTF-8"), \PDO::PARAM_INT);
            // exécution de la requête
            $ok = $req1->execute();
            
            // préparation de la requête de suppression de l'utilisateur
            $txt_req2 = "delete from tracegps_utilisateurs" ;
            $txt_req2 .= " where pseudo = :pseudo";
            $req2 = $this->cnx->prepare($txt_req2);
            // liaison de la requête et de ses paramètres
            $req2->bindValue("pseudo", mb_convert_encoding($pseudo, "UTF-8"), \PDO::PARAM_STR);
            // exécution de la requête
            $ok = $req2->execute();
            return $ok;
        }
    }
    
    
    // envoie un mail à l'utilisateur $pseudo avec son nouveau mot de passe $nouveauMdp
    // retourne true si envoi correct, false en cas de problème d'envoi
    // modifié par dP le 9/1/2018
    public function envoyerMdp($pseudo, $nouveauMdp) {
        global $ADR_MAIL_EMETTEUR;
        // si le pseudo n'est pas dans la table tracegps_utilisateurs :
        if ( $this->existePseudoUtilisateur($pseudo) == false ) return false;
        
        // recherche de l'adresse mail
        $adrMail = $this->getUnUtilisateur($pseudo)->getAdrMail();
        
        // envoie un mail à l'utilisateur avec son nouveau mot de passe
        $sujet = "Modification de votre mot de passe d'accès au service TraceGPS";
        $message = "Cher(chère) " . $pseudo . "\n\n";
        $message .= "Votre mot de passe d'accès au service service TraceGPS a été modifié.\n\n";
        $message .= "Votre nouveau mot de passe est : " . $nouveauMdp ;
        $ok = Outils::envoyerMail ($adrMail, $sujet, $message, $ADR_MAIL_EMETTEUR);
        return $ok;
    }
    
    // Le code restant à développer va être réparti entre les membres de l'équipe de développement.
    // Afin de limiter les conflits avec GitHub, il est décidé d'attribuer une zone de ce fichier à chaque développeur.
    // Développeur 1 : lignes 350 à 549
    // Développeur 2 : lignes 550 à 749
    // Développeur 3 : lignes 750 à 949
    // Développeur 4 : lignes 950 à 1150
    
    // Quelques conseils pour le travail collaboratif :
    // avant d'attaquer un cycle de développement (début de séance, nouvelle méthode, ...), faites un Pull pour récupérer 
    // la dernière version du fichier.
    // Après avoir testé et validé une méthode, faites un commit et un push pour transmettre cette version aux autres développeurs.
    
    
    
    
    
    // --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 1 (eric) : lignes 350 à 549
    // --------------------------------------------------------------------------------------
    
    public function getUneTrace($idTrace)
    {
        // préparation de la requête de recherche
        $txt_req = "Select id";
        $txt_req .= " from tracegps_vue_traces";
        $txt_req .= " where id = :id";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("id", $idTrace, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // libère les ressources du jeu de données
        $req->closeCursor();
        
        
        if($idTrace==$uneLigne->id){
            //$desPointDeTrace=array();
            $desPointDeTrace = DAO::getLesPointsDeTrace($idTrace);
            // préparation de la requête de recherche
            $txt_req = "Select dateDebut, dateFin, terminee, idUtilisateur";
            $txt_req .= " from tracegps_vue_traces";
            $txt_req .= " where id = :id";
            $req = $this->cnx->prepare($txt_req);
            // liaison de la requête et de ses paramètres
            $req->bindValue("id", $idTrace, \PDO::PARAM_STR);
            // extraction des données
            $req->execute();
            $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
            // libère les ressources du jeu de données
            $req->closeCursor();
            
            
            $uneDateHeureDebut=$uneLigne->dateDebut;
            $uneDateHeureFin=$uneLigne->dateFin;
            $estTerminee=$uneLigne->terminee;
            if ($estTerminee==1){$terminee=true;}else{$terminee=false;}
            $unIdUtilisateur=$uneLigne->idUtilisateur;
            
            $uneTrace =new Trace($idTrace, $uneDateHeureDebut, $uneDateHeureFin, $terminee, $unIdUtilisateur);
            
            foreach($desPointDeTrace as $unPointDeTrace)
            {
                $uneTrace->ajouterPoint($unPointDeTrace);
            }
            
            return $uneTrace;
        }
        else{return null;}
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
// --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 2 (jean) : lignes 550 à 749
    // --------------------------------------------------------------------------------------
    
    public function getLesPointsDeTrace($idTrace) {
        
        $lesPoints = array();
        
        $txt_req = "SELECT idTrace, id, latitude,longitude,altitude,dateHeure,rythmeCardio FROM tracegps_points";
        $txt_req .= " where idTrace = :id";
        $req = $this->cnx->prepare($txt_req);
        $req -> bindvalue ('id',$idTrace,\PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        
        
        
        // libère les ressources du jeu de données
        
        while ($uneLigne) {
            $unIdTrace = ($uneLigne->idTrace);
            $unId = ($uneLigne->id);
            $uneLatitude =  ($uneLigne->latitude);
            $uneLongitude =  ($uneLigne->longitude);
            $uneAltitude = ($uneLigne->altitude);
            $unedateHeure = ($uneLigne->dateHeure);
            $unRythmeCardio = ($uneLigne->rythmeCardio);
            $unTempsCumule = 0;
            $uneDistanceCumulee = 0;
            $uneVitesse = 0;
            
            $unPoint = new PointDeTrace ($unIdTrace,$unId,$uneLatitude,$uneLongitude,$uneAltitude,$unedateHeure,$unRythmeCardio,$unTempsCumule,$uneDistanceCumulee,$uneVitesse);
            
            $lesPoints[] = $unPoint;
            $uneLigne = $req->fetch(\PDO::FETCH_OBJ);}
            // fourniture de la collection
            $req->closeCursor();
            return $lesPoints;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
// --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 3 (noe) : lignes 750 à 949
    // --------------------------------------------------------------------------------------
    
    
    
    // Infos sur Teams : tableau excel des tâches
    // Infos sur SkolStlenneg : AP 2.6
    // creerUneTrace(Trace $uneTrace) : enregistre la trace $uneTrace dans la bdd
    public function creerUneTrace($uneTrace){
        // Intégration de la trace à la BDD
        $txt_req = "INSERT INTO tracegps_traces(dateDebut,dateFin,terminee,idUtilisateur) values (:uneDateHeureDebut,:uneDateHeureFin,:terminee,:unIdUtilisateur)";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("uneDateHeureDebut", mb_convert_encoding($uneTrace->getDateHeureDebut(), "UTF-8"), \PDO::PARAM_STR);
        $req->bindValue("uneDateHeureFin", mb_convert_encoding($uneTrace->getDateHeureFin(), "UTF-8"), \PDO::PARAM_STR);
        $req->bindValue("terminee", mb_convert_encoding($uneTrace->getTerminee(), "UTF-8"), \PDO::PARAM_STR);
        $req->bindValue("unIdUtilisateur", mb_convert_encoding($uneTrace->getIdUtilisateur(), "UTF-8"), \PDO::PARAM_STR);
        // extraction des données
        $ok = $req->execute();
        
        $reponse = true;
        
        if (! $ok){
            $reponse = false;
        }
        
        $unId = $this->cnx->lastInsertId(); // Pour la mise à jour de l'objet $uneTrace
        $uneTrace->setId($unId); // mise à jour de l'objet $uneTrace
        return $reponse;
    }
    
    // existeAdrMailUtilisateur($adrmail) : fournit true si l'adresse mail $adrMail existe dans la table tracegps_utilisateurs, false sinon
    public function existeAdrMailUtilisateur($adrmail){
        $txt_req = "Select adrmail from tracegps_utilisateurs";
        $txt_req .= " where adrmail = :adrmail";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("adrmail", $adrmail, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // traitement de la réponse
        $reponse = false;
        if ($uneLigne) {
            $reponse = true;
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la réponse
        return $reponse;
    }
    
    // supprimerUneTrace($idTrace) : supprime la trace d'identifiant $idTrace dans la bdd, ainsi que tous ses points
    public function supprimerUneTrace($idTrace){
        
        //Première requête, elle delete tous les points liés à la trace
        $txt_req = "DELETE FROM tracegps_points WHERE idTrace = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", $idTrace, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        
        //Deuxième requête, elle delete la trace
        $txt_req = "DELETE FROM tracegps_traces WHERE id = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", $idTrace, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        
        //Requêtes de vérification
        $reponse = true;
        
        $txt_req = "SELECT idTrace FROM tracegps_points WHERE idTrace = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", $idTrace, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // traitement de la réponse
        if ($uneLigne) {
            $reponse = false;
        }
        $txt_req = "SELECT id FROM tracegps_traces WHERE id = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", $idTrace, \PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(\PDO::FETCH_OBJ);
        // traitement de la réponse
        if ($uneLigne) {
            $reponse = false;
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la réponse
        return $reponse;
    }
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
        
    
    
    
    
    
    
    
    
    
    
    
    
   
    // --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 4 (julien) : lignes 950 à 1150
    // --------------------------------------------------------------------------------------
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    



    
} // fin de la classe DAO

// ATTENTION : on ne met pas de balise de fin de script pour ne pas prendre le risque
// d'enregistrer d'espaces après la balise de fin de script !!!!!!!!!!!!
