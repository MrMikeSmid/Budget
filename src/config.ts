export interface MailAccountConfig {
  id: string;
  imap: {
    host: string;
    port: number;
    secure: boolean;
    user: string;
    pass: string;
  };
  smtp: {
    host: string;
    port: number;
    secure: boolean;
    user: string;
    pass: string;
    fromAddress: string;
    fromName?: string;
  };
}

interface RawAccountJson {
  id?: string;
  imapHost: string;
  imapPort?: number;
  imapSecure?: boolean;
  imapUser: string;
  imapPass: string;
  smtpHost: string;
  smtpPort?: number;
  smtpSecure?: boolean;
  smtpUser?: string;
  smtpPass?: string;
  fromAddress?: string;
  fromName?: string;
}

function toAccountConfig(raw: RawAccountJson, fallbackId: string): MailAccountConfig {
  const imapUser = raw.imapUser;
  const imapPass = raw.imapPass;
  const smtpUser = raw.smtpUser ?? imapUser;
  const smtpPass = raw.smtpPass ?? imapPass;

  if (!raw.imapHost || !imapUser || !imapPass) {
    throw new Error(`Account "${fallbackId}" mist verplichte IMAP-configuratie (host/user/pass).`);
  }
  if (!raw.smtpHost) {
    throw new Error(`Account "${fallbackId}" mist verplichte SMTP-configuratie (host).`);
  }

  const smtpPort = raw.smtpPort ?? 587;

  return {
    id: raw.id ?? fallbackId,
    imap: {
      host: raw.imapHost,
      port: raw.imapPort ?? 993,
      secure: raw.imapSecure ?? true,
      user: imapUser,
      pass: imapPass,
    },
    smtp: {
      host: raw.smtpHost,
      port: smtpPort,
      secure: raw.smtpSecure ?? smtpPort === 465,
      user: smtpUser,
      pass: smtpPass,
      fromAddress: raw.fromAddress ?? smtpUser,
      fromName: raw.fromName,
    },
  };
}

function loadFromSingleAccountEnv(): MailAccountConfig[] {
  const imapHost = process.env.IMAP_HOST;
  const imapUser = process.env.IMAP_USER;
  const imapPass = process.env.IMAP_PASSWORD;
  const smtpHost = process.env.SMTP_HOST;

  if (!imapHost || !imapUser || !imapPass || !smtpHost) {
    return [];
  }

  const raw: RawAccountJson = {
    id: "default",
    imapHost,
    imapPort: process.env.IMAP_PORT ? Number(process.env.IMAP_PORT) : undefined,
    imapSecure: process.env.IMAP_SECURE ? process.env.IMAP_SECURE !== "false" : undefined,
    imapUser,
    imapPass,
    smtpHost,
    smtpPort: process.env.SMTP_PORT ? Number(process.env.SMTP_PORT) : undefined,
    smtpSecure: process.env.SMTP_SECURE ? process.env.SMTP_SECURE !== "false" : undefined,
    smtpUser: process.env.SMTP_USER,
    smtpPass: process.env.SMTP_PASSWORD,
    fromAddress: process.env.SMTP_FROM_ADDRESS,
    fromName: process.env.SMTP_FROM_NAME,
  };

  return [toAccountConfig(raw, "default")];
}

function loadFromMultiAccountJson(): MailAccountConfig[] {
  const json = process.env.MAIL_ACCOUNTS_JSON;
  if (!json) {
    return [];
  }

  let parsed: RawAccountJson[];
  try {
    parsed = JSON.parse(json);
  } catch (err) {
    throw new Error("MAIL_ACCOUNTS_JSON bevat geen geldige JSON.");
  }

  if (!Array.isArray(parsed) || parsed.length === 0) {
    throw new Error("MAIL_ACCOUNTS_JSON moet een niet-lege array van account-objecten zijn.");
  }

  return parsed.map((raw, index) => toAccountConfig(raw, raw.id ?? `account-${index + 1}`));
}

let cachedAccounts: MailAccountConfig[] | null = null;

export function loadMailAccounts(): MailAccountConfig[] {
  if (cachedAccounts) {
    return cachedAccounts;
  }

  const multi = loadFromMultiAccountJson();
  const accounts = multi.length > 0 ? multi : loadFromSingleAccountEnv();

  if (accounts.length === 0) {
    throw new Error(
      "Geen e-mailaccount geconfigureerd. Zet IMAP_HOST/IMAP_USER/IMAP_PASSWORD/SMTP_HOST " +
        "(en verwante variabelen) of MAIL_ACCOUNTS_JSON in de omgeving."
    );
  }

  const ids = new Set<string>();
  for (const account of accounts) {
    if (ids.has(account.id)) {
      throw new Error(`Dubbel account-id gevonden in configuratie: "${account.id}".`);
    }
    ids.add(account.id);
  }

  cachedAccounts = accounts;
  return accounts;
}

export function getMailAccount(accountId?: string): MailAccountConfig {
  const accounts = loadMailAccounts();
  if (!accountId) {
    return accounts[0];
  }
  const found = accounts.find((a) => a.id === accountId);
  if (!found) {
    const available = accounts.map((a) => a.id).join(", ");
    throw new Error(`Onbekend account-id "${accountId}". Beschikbare accounts: ${available}.`);
  }
  return found;
}

export function getBearerToken(): string {
  const token = process.env.MCP_BEARER_TOKEN;
  if (!token) {
    throw new Error("MCP_BEARER_TOKEN is niet ingesteld. Zet deze environment variable voordat je de server start.");
  }
  return token;
}
