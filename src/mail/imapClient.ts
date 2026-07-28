import { ImapFlow, type ImapFlowOptions } from "imapflow";
import type { MailAccountConfig } from "../config.js";

function buildImapOptions(account: MailAccountConfig): ImapFlowOptions {
  return {
    host: account.imap.host,
    port: account.imap.port,
    secure: account.imap.secure,
    auth: {
      user: account.imap.user,
      pass: account.imap.pass,
    },
    logger: false,
  };
}

/**
 * Opens a fresh IMAP connection for the duration of the callback and always
 * logs out afterwards. Nothing is cached or kept alive between requests, so
 * mail content is never persisted on the server.
 */
export async function withImapClient<T>(
  account: MailAccountConfig,
  callback: (client: ImapFlow) => Promise<T>
): Promise<T> {
  const client = new ImapFlow(buildImapOptions(account));

  try {
    await client.connect();
  } catch (err) {
    throw new Error(
      `Kan geen verbinding maken met IMAP-server ${account.imap.host}:${account.imap.port} (${describeError(err)}).`
    );
  }

  try {
    return await callback(client);
  } finally {
    try {
      await client.logout();
    } catch {
      client.close();
    }
  }
}

export function describeError(err: unknown): string {
  if (err instanceof Error) {
    return err.message;
  }
  return String(err);
}
