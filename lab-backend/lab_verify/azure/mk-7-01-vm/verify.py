import json
from pathlib import Path
from azure.identity import DefaultAzureCredential
from azure.mgmt.compute import ComputeManagementClient
from azure.mgmt.network import NetworkManagementClient
from azure.core.exceptions import ResourceNotFoundError
import logging
import sys
import os

# Add parent directory to path to import azure_helpers
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../../..')))
from azure_helpers import get_azure_credential


def run_verification(user: str, lab: str, email: str, subscription_id: str) -> dict:
    try:
        resource_group = f"{user}"
        logging.info(f"[VERIFY] Starting verification for user={user}, lab={lab}, rg={resource_group}")
        
        spec_path = Path(__file__).parent / "lab_spec.json"

        with open(spec_path, "r", encoding="utf-8") as f:
            spec = json.load(f)

        checks = spec["checks"]
        
        logging.info(f"[VERIFY] Initializing Azure clients for subscription={subscription_id}")
        credential = get_azure_credential()
        compute = ComputeManagementClient(credential, subscription_id)
        network = NetworkManagementClient(credential, subscription_id)
        logging.info(f"[VERIFY] Azure clients initialized successfully")

        # ✅ VM ellenőrzés
        vm_spec = checks["vm"]
        logging.info(f"[VERIFY] Checking VMs with prefix={vm_spec['prefix']}, expected_count={vm_spec['count']}")
        
        try:
            # Listázd az összes VM-et a resource groupban
            logging.info(f"[VERIFY] Listing VMs in resource group={resource_group}")
            vms = list(compute.virtual_machines.list(resource_group))
            logging.info(f"[VERIFY] Found {len(vms)} total VMs in resource group")
            
            matching_vms = [v for v in vms if v.name.startswith(vm_spec["prefix"])]
            logging.info(f"[VERIFY] Found {len(matching_vms)} matching VMs: {[v.name for v in matching_vms]}")

            # Ellenőrizd, hogy a megfelelő számú VM létezik-e
            if len(matching_vms) < vm_spec["count"]:
                msg = f"Nem található elegendő VM, amely '{vm_spec['prefix']}' prefixszel kezdődik a resource groupban '{resource_group}'. Elvárt: {vm_spec['count']}, Talált: {len(matching_vms)}"
                logging.warning(f"[VERIFY] {msg}")
                return {
                    "success": False,
                    "message": msg,
                }

            # Ellenőrizd az egyes VM-ek méretét és OS típusát
            for vm in matching_vms:
                logging.info(f"[VERIFY] Checking VM {vm.name}: size={vm.hardware_profile.vm_size}, os_type={vm.storage_profile.os_disk.os_type}")
                
                if vm.hardware_profile.vm_size != vm_spec["size"]:
                    msg = f"VM méret hibás: {vm.name} - Elvárt: {vm_spec['size']}, Talált: {vm.hardware_profile.vm_size}"
                    logging.warning(f"[VERIFY] {msg}")
                    return {
                        "success": False,
                        "message": msg,
                    }

                if vm.storage_profile.os_disk.os_type != vm_spec["os_type"]:
                    msg = f"OS típus hibás: {vm.name} - Elvárt: {vm_spec['os_type']}, Talált: {vm.storage_profile.os_disk.os_type}"
                    logging.warning(f"[VERIFY] {msg}")
                    return {
                        "success": False,
                        "message": msg,
                    }
            
            logging.info(f"[VERIFY] All VM checks passed")

        except ResourceNotFoundError as e:
            msg = f"VM nem található a resource groupban '{resource_group}': {str(e)}"
            logging.error(f"[VERIFY] {msg}")
            return {
                "success": False,
                "message": msg,
            }

        # ✅ VNet ellenőrzés
        vnet_spec = checks["vnet"]
        logging.info(f"[VERIFY] Checking VNet with prefix={vnet_spec['prefix']}")
        
        try:
            # Listázd az összes VNet-et a resource groupban
            logging.info(f"[VERIFY] Listing VNets in resource group={resource_group}")
            vnets = list(network.virtual_networks.list(resource_group))
            logging.info(f"[VERIFY] Found {len(vnets)} total VNets: {[v.name for v in vnets]}")
            
            vnet = next(
                (v for v in vnets if v.name.startswith(vnet_spec["prefix"])), None
            )

            if not vnet:
                msg = f"Nem található olyan VNet, amely '{vnet_spec['prefix']}' prefixszel kezdődik a resource groupban '{resource_group}'."
                logging.warning(f"[VERIFY] {msg}")
                return {
                    "success": False,
                    "message": msg,
                }
        except ResourceNotFoundError as e:
            msg = f"VNet nem található a resource groupban '{resource_group}': {str(e)}"
            logging.error(f"[VERIFY] {msg}")
            return {
                "success": False,
                "message": msg,
            }

        # Ellenőrizd, hogy a VNet neve helyes prefixszel kezdődik-e
        logging.info(f"[VERIFY] Found VNet: {vnet.name}")
        if not vnet.name.startswith(vnet_spec["prefix"]):
            msg = f"VNet neve hibás: Elvárt prefix: {vnet_spec['prefix']}, Talált: {vnet.name}"
            logging.warning(f"[VERIFY] {msg}")
            return {"success": False, "message": msg}
        
        logging.info(f"[VERIFY] All VNet checks passed")
        logging.info(f"[VERIFY] Verification completed successfully for user={user}")
        return {"success": True, "message": "Lab sikeresen ellenőrizve."}

    except Exception as e:
        # Bármilyen más hiba szép JSON válaszként
        logging.error(f"[VERIFY] Unexpected error during verification: {str(e)}", exc_info=True)
        return {"success": False, "message": f"Hiba történt az ellenőrzés során: {str(e)}"}
