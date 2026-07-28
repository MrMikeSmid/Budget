import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { getMailAccount } from "../config.js";
import { withImapClient, describeError } from "../mail/imapClient.js";
import { toSummary, jsonResult, errorResult } from "./shared.js";

const inputShape = {
  folder: z.string().optional().describe('IMAP-map om te doorzoeken, standaard "INBOX".'),
  limit: z.number().int().min(1).max(100).optional().describe("Maximum aantal e-mails, standaard 20, max 100."),
  unseenOnly: z.boolean().optional().describe("Alleen ongelezen e-mails teruggeven."),
  account: z.string().optional().describe("Account-id, alleen nodig bij meerdere geconfigureerde accounts."),
};

export function registerListEmailsTool(server: McpServer): void {
  server.registerTool(
    "list_emails",
    {
      title: "Lijst recente e-mails",
      description:
        "Haalt recente e-mails op uit een map (standaard INBOX), met basismetadata: afzender, ontvanger, onderwerp, datum en gelezen/ongelezen status. Inhoud wordt live via IMAP opgehaald, er wordt niets opgeslagen.",
      inputSchema: inputShape,
    },
    async (args) => {
      const folder = args.folder ?? "INBOX";
      const limit = args.limit ?? 20;

      try {
        const account = getMailAccount(args.account);

        return await withImapClient(account, async (client) => {
          const mailbox = await client.mailboxOpen(folder);
          const exists = mailbox.exists;

          if (exists === 0) {
            return jsonResult({ folder, total: 0, emails: [] });
          }

          let uids: number[];

          if (args.unseenOnly) {
            const found = await client.search({ seen: false }, { uid: true });
            uids = (found === false ? [] : found).slice(-limit);
          } else {
            const start = Math.max(1, exists - limit + 1);
            const seqRange = `${start}:${exists}`;
            const collected: number[] = [];
            for await (const msg of client.fetch(seqRange, { uid: true })) {
              collected.push(msg.uid);
            }
            uids = collected;
          }

          if (uids.length === 0) {
            return jsonResult({ folder, total: exists, emails: [] });
          }

          const summaries = [];
          for await (const msg of client.fetch(uids, { envelope: true, flags: true, uid: true, size: true }, { uid: true })) {
            summaries.push(toSummary(msg));
          }
          summaries.sort((a, b) => b.id - a.id);

          return jsonResult({ folder, total: exists, emails: summaries });
        });
      } catch (err) {
        return errorResult(`Kon e-mails niet ophalen: ${describeError(err)}`);
      }
    }
  );
}
