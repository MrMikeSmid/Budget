import nodemailer, { type Transporter } from "nodemailer";
import type { MailAccountConfig } from "../config.js";
import { describeError } from "./imapClient.js";

export interface SendEmailInput {
  to: string | string[];
  subject: string;
  text?: string;
  html?: string;
  cc?: string | string[];
  bcc?: string | string[];
}

function createTransporter(account: MailAccountConfig): Transporter {
  return nodemailer.createTransport({
    host: account.smtp.host,
    port: account.smtp.port,
    secure: account.smtp.secure,
    auth: {
      user: account.smtp.user,
      pass: account.smtp.pass,
    },
  });
}

export async function sendMailViaAccount(
  account: MailAccountConfig,
  input: SendEmailInput
): Promise<{ messageId: string }> {
  const transporter = createTransporter(account);

  const from = account.smtp.fromName
    ? `"${account.smtp.fromName}" <${account.smtp.fromAddress}>`
    : account.smtp.fromAddress;

  try {
    const info = await transporter.sendMail({
      from,
      to: input.to,
      cc: input.cc,
      bcc: input.bcc,
      subject: input.subject,
      text: input.text,
      html: input.html,
    });
    return { messageId: info.messageId };
  } catch (err) {
    throw new Error(
      `Versturen via SMTP-server ${account.smtp.host}:${account.smtp.port} is mislukt (${describeError(err)}).`
    );
  } finally {
    transporter.close();
  }
}
