// Общие типы данных, приходящих с API.

// Данные текущего пользователя (ответ /api/me и /api/login).
export type Me = {
  id: number
  phone: string
  name: string
  nickname: string | null
  displayName: string // ник, если задан, иначе ФИО
  telegram: string | null // без ведущего @
  avatarUrl: string | null // относительный URL или null (тогда буква-аватар)
  email: string | null
  emailVerified: boolean
  rttfRating: number | null // рейтинг на rttf.ru; null = нет рейтинга
  roles: string[]
  isChampion: boolean
}

// Моя запись на турнир (или null, если не записан / снялся).
export type MyEntry = {
  status: 'registered' | 'waitlisted' | 'cancelled' | 'dropped'
  checkedIn: boolean
  waitlistPosition: number | null
}

// Турнир с расписанием, счётчиками и моим статусом.
export type Tournament = {
  id: number
  number: number // порядковый номер от первого турнира (#1 = 15.03.2026)
  name: string
  date: string // YYYY-MM-DD
  status: string
  capacity: number
  registeredCount: number
  waitlistCount: number
  registrationOpensAt: string // ISO
  checkinStartsAt: string
  checkinEndsAt: string
  registrationOpen: boolean
  checkinOpen: boolean
  me: MyEntry | null
}

// Участник в публичном списке.
export type Participant = {
  name: string
  avatarUrl: string | null
  checkedIn: boolean
}

export type Participants = {
  number: number
  date: string
  status: string
  capacity: number
  registered: Participant[]
  waitlist: Participant[]
}

// Строка ростера на экране чекина у админа.
export type RosterEntry = {
  userId: number
  name: string
  phone: string
  checkedIn: boolean
}

// Выступление игрока в одном турнире (для личного кабинета).
export type MyTournamentStat = {
  id: number
  number: number
  date: string
  status: string
  tableNumber: number
  stage: string
  games: number
  wins: number
  losses: number
}

// Строка таблицы статистики.
export type PlayerStat = {
  userId: number
  name: string
  avatarUrl: string | null
  games: number
  wins: number
  losses: number
  points: number
}

// --- Турнирная сетка ---
export type BracketPlayer = { id: number; name: string } | null

export type BracketMatch = {
  id: number
  slot: number
  player1: BracketPlayer
  player2: BracketPlayer
  winnerId: number | null
  status: 'pending' | 'done'
  walkover: boolean
}

// Проигравший на столе 1 — кандидат на подсадку в bye-слот стола 2.
export type Table1Loser = { id: number; name: string }

export type BracketRound = {
  round: number
  label: string
  matches: BracketMatch[]
}

export type BracketTable = {
  tableNumber: number
  rounds: BracketRound[]
}

export type Bracket = {
  tournament: { id: number; number: number; date: string; status: string }
  tables: BracketTable[]
}

export type Roster = {
  tournamentId: number
  status: string
  capacity: number
  registeredCount: number
  checkedInCount: number
  waitlistCount: number
  main: RosterEntry[]
  waitlist: RosterEntry[]
  closed?: { dropped: number; promoted: number }
}
