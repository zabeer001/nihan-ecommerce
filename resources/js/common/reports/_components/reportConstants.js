import {
  CheckCircle2,
  Hash,
  Users,
  UserCheck,
  XCircle,
  BarChart3,
} from 'lucide-react';

export const SUMMARY_CARDS = [
  {
    key: 'total_entries',
    label: 'Total Entries',
    description: 'All cataloged tapes in TapeDB.',
    icon: BarChart3,
  },
  {
    key: 'unique_titles',
    label: 'Unique Titles',
    description: 'Distinct title names in the catalog.',
    icon: Hash,
  },
  {
    key: 'qa_checked',
    label: 'QA Checked',
    description: 'Entries marked as QA checked.',
    icon: CheckCircle2,
  },
  {
    key: 'not_qa_checked',
    label: 'Pending QA',
    description: 'Entries still waiting for QA review.',
    icon: XCircle,
  },
  {
    key: 'editors_admins',
    label: 'Editors + Admins',
    description: 'Users with editor or admin access.',
    icon: Users,
  },
  {
    key: 'total_users',
    label: 'Registered Users',
    description: 'All registered accounts across every role.',
    icon: Users,
  },
  {
    key: 'users_with_tapes',
    label: 'Users With Tapes',
    description: 'Registered users who have added at least one tape.',
    icon: UserCheck,
  },
];

export const TECHNOLOGY_BADGES = [
  'Laravel',
  'Node.js',
  'Express',
  'React',
  'MongoDB',
  'MySQL',
  'Redis',
  'REST',
];
