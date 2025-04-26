import json
from pathlib import Path
from azure.identity import DefaultAzureCredential
from azure.mgmt.compute import ComputeManagementClient
from azure.mgmt.network import NetworkManagementClient

def run_verification(user: str, lab: str, email: str, subscription_id: str) -> dict:
    resource_group = f"{user}-{lab}-rg"
    spec_path = Path(__file__).parent / "lab_spec.json"

    with open(spec_path, "r", encoding="utf-8") as f:
        spec = json.load(f)

    checks = spec["checks"]
    credential = DefaultAzureCredential()
    compute = ComputeManagementClient(credential, subscription_id)
    network = NetworkManagementClient(credential, subscription_id)

    # ✅ VM ellenőrzés
    vm_spec = checks["vm"]
    vm = compute.virtual_machines.get(resource_group, vm_spec["name"])

    if vm.hardware_profile.vm_size != vm_spec["size"]:
        return {"success": False, "message": f"VM méret hibás: {vm.hardware_profile.vm_size}"}

    if vm.storage_profile.os_disk.disk_size_gb != vm_spec["os_disk_size"]:
        return {"success": False, "message": f"OS disk méret hibás: {vm.storage_profile.os_disk.disk_size_gb}"}

    if vm.storage_profile.os_disk.managed_disk.storage_account_type != vm_spec["os_disk_type"]:
        return {"success": False, "message": f"Disk típusa hibás: {vm.storage_profile.os_disk.managed_disk.storage_account_type}"}

    image = vm.storage_profile.image_reference
    expected_image = vm_spec["image"]
    for key in ["publisher", "offer", "sku", "version"]:
        if getattr(image, key) != expected_image[key]:
            return {"success": False, "message": f"VM image {key} hibás: {getattr(image, key)}"}

    if vm.storage_profile.os_disk.os_type.value != vm_spec["os_type"]:
        return {"success": False, "message": f"OS típus hibás: {vm.storage_profile.os_disk.os_type}"}

    # ✅ VNet ellenőrzés
    vnet_spec = checks["vnet"]
    vnet = network.virtual_networks.get(resource_group, vnet_spec["name"])
    if vnet.name != vnet_spec["name"]:
        return {"success": False, "message": f"VNet neve hibás: {vnet.name}"}

    return {"success": True, "message": "Lab sikeresen ellenőrizve"}
