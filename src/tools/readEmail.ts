import { z } from "zod";
import { simpleParser } from "mailparser";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { getMailAccount } from "../config.js";
import { withImapClient, describeError } from "../mail/imapClient.js";
import { formatAddressList, jsonResult, errorResult } from "./shared.js";

const inputShape = {
  id: z.union([z.string(), z.number()]).describe("De UID van de e-mail (zoals teruggegeven door list_emails/search_emails)."),
  folder: z.string().optional().describe('IMAP-map waarin de e-mail staat, standaard "INBOX".'),
  account: z.string().optional().describe("Account-id, alleen nodig bij meerdere geconfigureerde accounts."),
};

export function registerReadEmailTool(server: McpServer): void {
  server.registerTool(
    "read_email",
    {
      title: "Lees volledige e-mail",
      description:
        "Haalt de volledige inhoud van één e-mail op (tekst/HTML-body, headers, bijlagenamen) op basis van UID. Wordt live via IMAP opgehaald, niets wordt op de server bewaard.",
      inputSchema: inputShape,
    },
    async (args) => {
      const folder = args.folder ?? "INBOX";
      const uid = Number(args.id);

      if (!Number.isInteger(uid) || uid <= 0) {
        return errorResult(`Ongeldige e-mail-id: "${args.id}". Verwacht wordt een numerieke UID.`);
      }

      try {
        const account = getMailAccount(args.account);

        return await withImapClient(account, async (client) => {
          await client.mailboxOpen(folder);

          const message = await client.fetchOne(
            String(uid),
            { envelope: true, uid: true, flags: true, size: true, source: true },
            { uid: true }
          );

          if (!message || !message.source) {
            return errorResult(`Geen e-mail gevonden met UID ${uid} in map "${folder}".`);
          }

          const parsed = await simpleParser(message.source);

          return jsonResult({
            id: message.uid,
            folder,
            from: formatAddressList(message.envelope?.from),
            to: formatAddressList(message.envelope?.to),
            cc: formatAddressList(message.envelope?.cc),
            subject: message.envelope?.subject ?? "(geen onderwerp)",
            date: message.envelope?.date ? new Date(message.envelope.date).toISOString() : null,
            unread: message.flags ? !message.flags.has("\\Seen") : true,
            text: parsed.text ?? null,
            html: typeof parsed.html === "string" ? parsed.html : null,
            attachments: parsed.attachments.map((a) => ({
              filename: a.filename ?? "(zonder naam)",
              contentType: a.contentType,
              size: a.size,
            })),
          });
        });
      } catch (err) {
        return errorResult(`Kon e-mail niet lezen: ${describeError(err)}`);
      }
    }
  );
}
