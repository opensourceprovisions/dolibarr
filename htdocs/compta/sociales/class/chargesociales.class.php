<?php
/* Copyright (C) 2002      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2007 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2016      Frédéric France      <frederic.france@free.fr>
 * Copyright (C) 2017      Alexandre Spangaro	<aspangaro@zendsi.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/compta/sociales/class/chargesociales.class.php
 *		\ingroup    facture
 *		\brief      Fichier de la classe des charges sociales
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';


/** 
 *	Classe permettant la gestion des paiements des charges
 *  La tva collectee n'est calculee que sur les factures payees.
 */
class ChargeSociales extends CommonObject
{
    public $element='chargesociales';
    public $table='chargesociales';
    public $table_element='chargesociales';
    public $picto = 'bill';
    
    /**
     * {@inheritdoc}
     */
    protected $table_ref_field = 'ref';

    var $date_ech;
    var $lib;
    var $type;
    var $type_libelle;
    var $amount;
    var $paye;
    var $periode;
    var $date_creation;
    var $date_modification;
    var $date_validation;
    var $fk_account;
	var $fk_project;


    /**
     * Constructor
     *
     * @param	DoliDB		$db		Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }

    /**
     *  Retrouve et charge une charge sociale
     *
     *  @param	int     $id		Id
     *  @param	string  $ref	Ref
     *  @return	int <0 KO >0 OK
     */
    function fetch($id, $ref='')
    {
        $sql = "SELECT cs.rowid, cs.date_ech";
        $sql.= ", cs.libelle as lib, cs.fk_type, cs.amount, cs.fk_projet as fk_project, cs.paye, cs.periode, cs.import_key";
        $sql.= ", cs.fk_account, cs.fk_mode_reglement";
        $sql.= ", c.libelle";
        $sql.= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
        $sql.= " FROM ".MAIN_DB_PREFIX."chargesociales as cs";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_chargesociales as c ON cs.fk_type = c.id";
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as p ON cs.fk_mode_reglement = p.id';
        if ($ref) $sql.= " WHERE cs.rowid = ".$ref;
        else $sql.= " WHERE cs.rowid = ".$id;

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id					= $obj->rowid;
                $this->ref					= $obj->rowid;
                $this->date_ech				= $this->db->jdate($obj->date_ech);
                $this->lib					= $obj->lib;
                $this->type					= $obj->fk_type;
                $this->type_libelle			= $obj->libelle;
                $this->fk_account			= $obj->fk_account;
                $this->mode_reglement_id	= $obj->fk_mode_reglement;
                $this->mode_reglement_code	= $obj->mode_reglement_code;
                $this->mode_reglement		= $obj->mode_reglement_libelle;
                $this->amount				= $obj->amount;
				$this->fk_project			= $obj->fk_project;
                $this->paye					= $obj->paye;
                $this->periode				= $this->db->jdate($obj->periode);
                $this->import_key			= $this->import_key;
                
                $this->db->free($resql);

                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            $this->error=$this->db->lasterror();
            return -1;
        }
    }

	/**
	 * Check if a social contribution can be created into database
	 *
	 * @return	boolean		True or false
	 */
	function check()
	{
		$newamount=price2num($this->amount,'MT');

        // Validation parametres
        if (! $newamount > 0 || empty($this->date_ech) || empty($this->periode))
        {
            return false;
        }


		return true;
	}

    /**
     *      Create a social contribution into database
     *
     *      @param	User	$user   User making creation
     *      @return int     		<0 if KO, id if OK
     */
    function create($user)
    {
    	global $conf;

        $now=dol_now();

        // Nettoyage parametres
        $newamount=price2num($this->amount,'MT');

		if (!$this->check())
		{
			 $this->error="ErrorBadParameter";
			 return -2;
		}

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chargesociales (fk_type, fk_account, fk_mode_reglement, libelle, date_ech, periode, amount, fk_projet, entity, fk_user_author, date_creation)";
        $sql.= " VALUES (".$this->type;
        $sql.= ", ".($this->fk_account>0?$this->fk_account:'NULL');
        $sql.= ", ".($this->mode_reglement_id>0?"'".$this->mode_reglement_id."'":"NULL");
        $sql.= ", '".$this->db->escape($this->lib)."'";
        $sql.= ", '".$this->db->idate($this->date_ech)."'";
		$sql.= ", '".$this->db->idate($this->periode)."'";
        $sql.= ", '".price2num($newamount)."'";
		$sql.= ", ".($this->fk_project>0?$this->fk_project:'NULL');
        $sql.= ", ".$conf->entity;
        $sql.= ", ".$user->id;
        $sql.= ", '".$this->db->idate($now)."'";
        $sql.= ")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->id=$this->db->last_insert_id(MAIN_DB_PREFIX."chargesociales");

            //dol_syslog("ChargesSociales::create this->id=".$this->id);
            $this->db->commit();
            return $this->id;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            return -1;
        }
    }


    /**
     *      Delete a social contribution
     *
     *      @param		User    $user   Object user making delete
     *      @return     		int 	<0 if KO, >0 if OK
     */
    function delete($user)
    {
        $error=0;

        $this->db->begin();

        // Get bank transaction lines for this social contributions
        include_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
        $account=new Account($this->db);
        $lines_url=$account->get_url('',$this->id,'sc');

        // Delete bank urls
        foreach ($lines_url as $line_url)
        {
            if (! $error)
            {
                $accountline=new AccountLine($this->db);
                $accountline->fetch($line_url['fk_bank']);
                $result=$accountline->delete_urls($user);
                if ($result < 0)
                {
                    $error++;
                }
            }
        }

        // Delete payments
        if (! $error)
        {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."paiementcharge WHERE fk_charge=".$this->id;
            dol_syslog(get_class($this)."::delete", LOG_DEBUG);
            $resql=$this->db->query($sql);
            if (! $resql)
            {
                $error++;
                $this->error=$this->db->lasterror();
            }
        }

        if (! $error)
        {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."chargesociales WHERE rowid=".$this->id;
            dol_syslog(get_class($this)."::delete", LOG_DEBUG);
            $resql=$this->db->query($sql);
            if (! $resql)
            {
                $error++;
                $this->error=$this->db->lasterror();
            }
        }

        if (! $error)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }

    }


    /**
     *      Met a jour une charge sociale
     *
     *      @param	User	$user   Utilisateur qui modifie
     *      @return int     		<0 si erreur, >0 si ok
     */
    function update($user)
    {
        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."chargesociales";
        $sql.= " SET libelle='".$this->db->escape($this->lib)."'";
        $sql.= ", date_ech='".$this->db->idate($this->date_ech)."'";
        $sql.= ", periode='".$this->db->idate($this->periode)."'";
        $sql.= ", amount='".price2num($this->amount,'MT')."'";
		$sql.= ", fk_projet='".$this->db->escape($this->fk_project)."'";
        $sql.= ", fk_user_modif=".$user->id;
        $sql.= " WHERE rowid=".$this->id;

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Calculate amount remaining to pay by year
     *
     * @param	int		$year		Year
     * @return	number
     */
    function solde($year = 0)
    {
    	global $conf;

        $sql = "SELECT SUM(f.amount) as amount";
        $sql.= " FROM ".MAIN_DB_PREFIX."chargesociales as f";
        $sql.= " WHERE f.entity = ".$conf->entity;
        $sql.= " AND paye = 0";

        if ($year) {
            $sql .= " AND f.datev >= '$y-01-01' AND f.datev <= '$y-12-31' ";
        }

        $result = $this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $this->db->free($result);
                return $obj->amount;
            }
            else
            {
                return 0;
            }

        }
        else
        {
            print $this->db->error();
            return -1;
        }
    }

    /**
     *    Tag social contribution as payed completely
     *
     *    @param	User	$user       Object user making change
     *    @return	int					<0 if KO, >0 if OK
     */
    function set_paid($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."chargesociales SET";
        $sql.= " paye = 1";
        $sql.= " WHERE rowid = ".$this->id;
        $return = $this->db->query($sql);
        if ($return) return 1;
        else return -1;
    }
    /**
     *    Remove tag payed on social contribution
     *
     *    @param	User	$user       Object user making change
     *    @return	int					<0 if KO, >0 if OK
     */
    function set_unpaid($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."chargesociales SET";
        $sql.= " paye = 0";
        $sql.= " WHERE rowid = ".$this->id;
        $return = $this->db->query($sql);
        if ($return) return 1;
        else return -1;
    }
    
    /**
     *  Retourne le libelle du statut d'une charge (impaye, payee)
     *
     *  @param	int		$mode       	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=short label + picto, 6=Long label + picto
	 *  @param  double	$alreadypaid	0=No payment already done, >0=Some payments were already done (we recommand to put here amount payed if you have it, 1 otherwise)
     *  @return	string        			Label
     */
    function getLibStatut($mode=0,$alreadypaid=-1)
    {
        return $this->LibStatut($this->paye,$mode,$alreadypaid);
    }

    /**
     *  Renvoi le libelle d'un statut donne
     *
     *  @param	int		$statut        	Id statut
     *  @param  int		$mode          	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=short label + picto, 6=Long label + picto
	 *  @param  double	$alreadypaid	0=No payment already done, >0=Some payments were already done (we recommand to put here amount payed if you have it, 1 otherwise)
     *  @return string        			Label
     */
    function LibStatut($statut,$mode=0,$alreadypaid=-1)
    {
        global $langs;
        $langs->load('customers');
        $langs->load('bills');

        if ($mode == 0)
        {
            if ($statut ==  0) return $langs->trans("Unpaid");
            if ($statut ==  1) return $langs->trans("Paid");
        }
        if ($mode == 1)
        {
            if ($statut ==  0) return $langs->trans("Unpaid");
            if ($statut ==  1) return $langs->trans("Paid");
        }
        if ($mode == 2)
        {
            if ($statut ==  0 && $alreadypaid <= 0) return img_picto($langs->trans("Unpaid"), 'statut1').' '.$langs->trans("Unpaid");
            if ($statut ==  0 && $alreadypaid > 0) return img_picto($langs->trans("BillStatusStarted"), 'statut3').' '.$langs->trans("BillStatusStarted");
            if ($statut ==  1) return img_picto($langs->trans("Paid"), 'statut6').' '.$langs->trans("Paid");
        }
        if ($mode == 3)
        {
            if ($statut ==  0 && $alreadypaid <= 0) return img_picto($langs->trans("Unpaid"), 'statut1');
            if ($statut ==  0 && $alreadypaid > 0) return img_picto($langs->trans("BillStatusStarted"), 'statut3');
            if ($statut ==  1) return img_picto($langs->trans("Paid"), 'statut6');
        }
        if ($mode == 4)
        {
            if ($statut ==  0 && $alreadypaid <= 0) return img_picto($langs->trans("Unpaid"), 'statut1').' '.$langs->trans("Unpaid");
            if ($statut ==  0 && $alreadypaid > 0) return img_picto($langs->trans("BillStatusStarted"), 'statut3').' '.$langs->trans("BillStatusStarted");
            if ($statut ==  1) return img_picto($langs->trans("Paid"), 'statut6').' '.$langs->trans("Paid");
        }
        if ($mode == 5)
        {
            if ($statut ==  0 && $alreadypaid <= 0) return $langs->trans("Unpaid").' '.img_picto($langs->trans("Unpaid"), 'statut1');
            if ($statut ==  0 && $alreadypaid > 0) return $langs->trans("BillStatusStarted").' '.img_picto($langs->trans("BillStatusStarted"), 'statut3');
            if ($statut ==  1) return $langs->trans("Paid").' '.img_picto($langs->trans("Paid"), 'statut6');
        }
        if ($mode == 6)
        {
            if ($statut ==  0 && $alreadypaid <= 0) return $langs->trans("Unpaid").' '.img_picto($langs->trans("Unpaid"), 'statut1');
            if ($statut ==  0 && $alreadypaid > 0) return $langs->trans("BillStatusStarted").' '.img_picto($langs->trans("BillStatusStarted"), 'statut3');
            if ($statut ==  1) return $langs->trans("Paid").' '.img_picto($langs->trans("Paid"), 'statut6');
        }
        
        return "Error, mode/status not found";
    }


    /**
     *  Return clicable name (with picto eventually)
     *
     *	@param	int		$withpicto		0=No picto, 1=Include picto into link, 2=Only picto
     * 	@param	int		$maxlen			Longueur max libelle
     *	@return	string					Chaine avec URL
     */
    function getNomUrl($withpicto=0,$maxlen=0)
    {
        global $langs;

        $result='';

        if (empty($this->ref)) $this->ref=$this->lib;
        $label = $langs->trans("ShowSocialContribution").': '.$this->ref;

        $link = '<a href="'.DOL_URL_ROOT.'/compta/sociales/card.php?id='.$this->id.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend='</a>';

        if ($withpicto) $result.=($link.img_object($label, 'bill', 'class="classfortooltip"').$linkend.' ');
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.($maxlen?dol_trunc($this->ref,$maxlen):$this->ref).$linkend;
        return $result;
    }

    /**
     * 	Return amount of payments already done
     *
     *	@return		int		Amount of payment already done, <0 if KO
     */
    function getSommePaiement()
    {
        $table='paiementcharge';
        $field='fk_charge';

        $sql = 'SELECT sum(amount) as amount';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$table;
        $sql.= ' WHERE '.$field.' = '.$this->id;

        dol_syslog(get_class($this)."::getSommePaiement", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $amount=0;

            $obj = $this->db->fetch_object($resql);
            if ($obj) $amount=$obj->amount?$obj->amount:0;

            $this->db->free($resql);
            return $amount;
        }
        else
        {
            return -1;
        }
    }

    /**
     * 	Charge les informations d'ordre info dans l'objet entrepot
     *
     *  @param	int		$id     Id of social contribution
     *  @return	int				<0 if KO, >0 if OK
     */
    function info($id)
    {
        $sql = "SELECT e.rowid, e.tms as datem, e.date_creation as datec, e.date_valid as datev, e.import_key,";
        $sql.= " e.fk_user_author, e.fk_user_modif, e.fk_user_valid";
		$sql.= " FROM ".MAIN_DB_PREFIX."chargesociales as e";
        $sql.= " WHERE e.rowid = ".$id;

        dol_syslog(get_class($this)."::info", LOG_DEBUG);
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);

                $this->id = $obj->rowid;

                if ($obj->fk_user_author) {
                    $cuser = new User($this->db);
                    $cuser->fetch($obj->fk_user_author);
                    $this->user_creation = $cuser;
                }

                if ($obj->fk_user_modif) {
                    $muser = new User($this->db);
                    $muser->fetch($obj->fk_user_modif);
                    $this->user_modification = $muser;
                }

                if ($obj->fk_user_valid) {
                    $vuser = new User($this->db);
                    $vuser->fetch($obj->fk_user_valid);
                    $this->user_validation = $vuser;
                }

                $this->date_creation     = $this->db->jdate($obj->datec);
                $this->date_modification = $this->db->jdate($obj->datem);
                $this->date_validation   = $this->db->jdate($obj->datev);
                $this->import_key        = $obj->import_key;
            }

            $this->db->free($result);

        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     *  Initialise an instance with random values.
     *  Used to build previews or test instances.
     *	id must be 0 if object instance is a specimen.
     *
     *  @return	void
     */
    function initAsSpecimen()
    {
        // Initialize parameters
        $this->id=0;
        $this->ref = 'SPECIMEN';
        $this->specimen=1;
        $this->paye = 0;
        $this->date = time();
        $this->date_ech=$this->date+3600*24*30;
        $this->periode=$this->date+3600*24*30;
        $this->amount=100;
        $this->lib = 0;
        $this->type = 1;
        $this->type_libelle = 'Social contribution label';
    }
}

