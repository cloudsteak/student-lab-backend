"""
Azure SDK helpers for handling timeouts and credential management
"""
import logging
from azure.identity import DefaultAzureCredential
from azure.core.credentials import AccessToken
from typing import Optional
import os

# Singleton credential instance to avoid recreating it multiple times
_credential_instance: Optional[DefaultAzureCredential] = None

def get_azure_credential() -> DefaultAzureCredential:
    """
    Get or create a singleton Azure credential instance.
    This prevents creating multiple credential objects which can cause issues.
    """
    global _credential_instance
    
    if _credential_instance is None:
        logging.info("[AZURE] Creating new DefaultAzureCredential instance")
        
        # Set timeout for credential acquisition
        _credential_instance = DefaultAzureCredential(
            # Exclude less common credential types to speed up authentication
            exclude_visual_studio_code_credential=True,
            exclude_shared_token_cache_credential=True,
            exclude_powershell_credential=True
        )
        logging.info("[AZURE] DefaultAzureCredential instance created successfully")
    
    return _credential_instance

def configure_azure_sdk_logging():
    """
    Configure Azure SDK logging for better debugging
    """
    # Set Azure SDK logging level
    azure_logger = logging.getLogger('azure')
    azure_logger.setLevel(logging.WARNING)  # Reduce noise from Azure SDK
    
    # Set specific loggers
    logging.getLogger('azure.core.pipeline.policies.http_logging_policy').setLevel(logging.WARNING)
    logging.getLogger('azure.identity').setLevel(logging.INFO)
