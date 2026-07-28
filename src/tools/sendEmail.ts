import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { getMailAccount } from "../config.js";
import { sendMailViaAccount } from "../mail/smtpClient.js";
import { describeError } from "../mail/imapClient.js";
import { jsonResult, errorResult } from "./shared.js";

const addressList = z.union([z.string(), z.array(z.string())]);

const inputShape = {
  to: addressList.describe("Ontvanger(s), als e-mailadres of lijst van e-mailadressen."),
  subject: z.string().describe("Onderwerp van de e-mail."),
  body: z.string().optional().describe("Platte-tekst inhoud van de e-mail."),
  html: z.string().optional().describe("HTML-inhoud van de e-mail (optioneel, naast of in plaats van body)."),
  cc: addressList.optional().describe("CC-ontvanger(s)."),
  bcc: addressList.optional().describe("BCC-ontvanger(s)."),
  account: z.string().optional().describe("Account-id, alleen nodig bij meerdere geconfigureerde accounts."),
};

export function registerSendEmailTool(server: McpServer): void {
  server.registerTool(
    "send_email",
    {
      title: "Verstuur e-mail",
      description: "Verstuurt een nieuwe e-mail via SMTP. Vereist minstens één van 'body' (platte tekst) of 'html'.",
      inputSchema: inputShape,
    },
    async (args) => {
      if (!args.body && !args.html) {
        return errorResult("Geef minstens 'body' (platte tekst) of 'html' op als inhoud van de e-mail.");
      }

      try {
        const account = getMailAccount(args.account);
        const result = await sendMailViaAccount(account, {
          to: args.to,
          subject: args.subject,
          text: args.body,
          html: args.html,
          cc: args.cc,
          bcc: args.bcc,
        });

        return jsonResult({ sent: true, messageId: result.messageId });
      } catch (err) {
        return errorResult(`Versturen van e-mail is mislukt: ${describeError(err)}`);
      }
    }
  );
}
