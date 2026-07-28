import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { SearchObject } from "imapflow";
import { getMailAccount } from "../config.js";
import { withImapClient, describeError } from "../mail/imapClient.js";
import { toSummary, jsonResult, errorResult } from "./shared.js";

const inputShape = {
  from: z.string().optional().describe("Zoek op (deel van) het afzender-adres of de naam."),
  subject: z.string().optional().describe("Zoek op (deel van) het onderwerp."),
  text: z.string().optional().describe("Zoek op tekst in de inhoud van de e-mail."),
  since: z.string().optional().describe("Alleen e-mails vanaf deze datum (ISO 8601, bv. 2026-01-01)."),
  before: z.string().optional().describe("Alleen e-mails tot deze datum (ISO 8601, bv. 2026-02-01)."),
  folder: z.string().optional().describe('IMAP-map om te doorzoeken, standaard "INBOX".'),
  limit: z.number().int().min(1).max(100).optional().describe("Maximum aantal resultaten, standaard 20, max 100."),
  account: z.string().optional().describe("Account-id, alleen nodig bij meerdere geconfigureerde accounts."),
};

export function registerSearchEmailsTool(server: McpServer): void {
  server.registerTool(
    "search_emails",
    {
      title: "Zoek e-mails",
      description:
        "Zoekt e-mails op afzender, onderwerp, inhoud en/of datumrange binnen een map. Minstens één zoekcriterium moet worden opgegeven.",
      inputSchema: inputShape,
    },
    async (args) => {
      const folder = args.folder ?? "INBOX";
      const limit = args.limit ?? 20;

      if (!args.from && !args.subject && !args.text && !args.since && !args.before) {
        return errorResult(
          "Geef minstens één zoekcriterium op: from, subject, text, since en/of before."
        );
      }

      const criteria: SearchObject = {};
      if (args.from) criteria.from = args.from;
      if (args.subject) criteria.subject = args.subject;
      if (args.text) criteria.body = args.text;

      if (args.since) {
        const since = new Date(args.since);
        if (Number.isNaN(since.getTime())) {
          return errorResult(`Ongeldige datum voor "since": "${args.since}".`);
        }
        criteria.since = since;
      }

      if (args.before) {
        const before = new Date(args.before);
        if (Number.isNaN(before.getTime())) {
          return errorResult(`Ongeldige datum voor "before": "${args.before}".`);
        }
        criteria.before = before;
      }

      try {
        const account = getMailAccount(args.account);

        return await withImapClient(account, async (client) => {
          await client.mailboxOpen(folder);

          const found = await client.search(criteria, { uid: true });
          const uids = (found === false ? [] : found).slice(-limit);

          if (uids.length === 0) {
            return jsonResult({ folder, criteria: args, emails: [] });
          }

          const summaries = [];
          for await (const msg of client.fetch(uids, { envelope: true, flags: true, uid: true, size: true }, { uid: true })) {
            summaries.push(toSummary(msg));
          }
          summaries.sort((a, b) => b.id - a.id);

          return jsonResult({ folder, criteria: args, emails: summaries });
        });
      } catch (err) {
        return errorResult(`Zoeken naar e-mails is mislukt: ${describeError(err)}`);
      }
    }
  );
}
