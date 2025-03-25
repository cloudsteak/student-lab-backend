# --- lab-backend/emailer.py ---

import sib_api_v3_sdk
from sib_api_v3_sdk.rest import ApiException
import os

def send_lab_ready_email(username: str, password: str, recipient: str):
    configuration = sib_api_v3_sdk.Configuration()
    configuration.api_key["api-key"] = os.getenv("BREVO_API_KEY")
    azure_portal_url = os.getenv("PORTAL_AZURE_URL")
    aws_portal_url = os.getenv("PORTAL_AWS_URL")

    api_instance = sib_api_v3_sdk.TransactionalEmailsApi(sib_api_v3_sdk.ApiClient(configuration))
    subject = "A labor környezeted elkészült!"
    sender = {"name": "CloudMentor", "email": os.getenv("EMAIL_SENDER")}
    to = [{"email": recipient}]
    html_content = f"""
    <p>Szia <b>{username}</b>!</p>
    <p>A lab környezeted elkészült. Jelentkezz be az <a href='https://portal.azure.com'>Azure Portal-ba</a>.</p>
    <p><b>Felhasználónév:</b> {username}<br><b>Jelszó:</b> {password}</p>
    <p>A lab 60 perc múlva automatikusan törlődik.</p>
    
    <p>Egyég linkek:</p>
    <ul>
        <li><a href='{azure_portal_url}' target='_blank'>Azure Portal</a></li>
        <li><a href='{aws_portal_url}' target='_blank'>AWS Console</a></li>
    
    <p>Üdv, <br> CloudMentor</p>
    """

    email = sib_api_v3_sdk.SendSmtpEmail(to=to, html_content=html_content, sender=sender, subject=subject)
    try:
        api_instance.send_transac_email(email)
    except ApiException as e:
        print(f"Hiba történt az e-mail küldéskor: {e}")
