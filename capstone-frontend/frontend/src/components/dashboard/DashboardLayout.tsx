import React, { useEffect, useState, type ComponentType } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Shield, 
  LogOut, 
  Menu, 
  X,
  Bell,
  Search
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuLabel, 
  DropdownMenuSeparator, 
  DropdownMenuTrigger 
} from '../ui/dropdown-menu';
import { roleLabels, panelLabels, type UserRole } from '../../types/rbac';
import { apiFetch } from '../../api';

type NavItem = {
  id: string;
  label: string;
  icon: ComponentType<{ className?: string }>;
};

type UserSearchResult = {
  name: string;
  email: string;
  roleLabel?: string;
  role?: string;
};

type PortalNotification = {
  id: string;
  label: string;
  count: number;
  targetView?: string;
  title?: string;
  body?: string;
  detail?: string;
  timestamp?: string;
};

function isUserSearchResult(user: unknown): user is UserSearchResult {
  return typeof user === 'object' &&
    user !== null &&
    'name' in user &&
    typeof user.name === 'string' &&
    'email' in user &&
    typeof user.email === 'string' &&
    (!('roleLabel' in user) || typeof user.roleLabel === 'string') &&
    (!('role' in user) || typeof user.role === 'string');
}

interface DashboardLayoutProps {
  children?: React.ReactNode;
  currentView: string;
  onNavigate: (view: string) => void;
  onLogout: () => void;
  role: UserRole;
  userEmail: string;
  navItems: NavItem[];
}

export const DashboardLayout: React.FC<DashboardLayoutProps> = ({
  children,
  currentView,
  onNavigate,
  onLogout,
  role,
  userEmail,
  navItems,
}: DashboardLayoutProps) => {
  const navigate = useNavigate();
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<UserSearchResult[]>([]);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isSearchLoading, setIsSearchLoading] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [notifications, setNotifications] = useState<PortalNotification[]>([]);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState<PortalNotification | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [showNotifications, setShowNotifications] = useState(false);
  const [scrollProgress, setScrollProgress] = useState(0);
  const [cookieAccepted, setCookieAccepted] = useState(() => localStorage.getItem('staff-cookie-notice') === 'accepted');
  const [schoolName, setSchoolName] = useState('University Attendance Portal');

  useEffect(() => {
    if (role !== 'super_admin') return;
    const controller = new AbortController();
    apiFetch('/api/admin/settings', { signal: controller.signal })
      .then(async response => response.ok ? response.json() : null)
      .then(body => body?.settings?.schoolName && setSchoolName(body.settings.schoolName))
      .catch(() => undefined);
    const updateSchoolName = (event: Event) => {
      const settings = (event as CustomEvent<{ schoolName?: string }>).detail;
      if (settings?.schoolName) setSchoolName(settings.schoolName);
    };
    window.addEventListener('school-settings-updated', updateSchoolName);
    return () => {
      controller.abort();
      window.removeEventListener('school-settings-updated', updateSchoolName);
    };
  }, [role]);

  useEffect(() => {
    const updateScroll = () => {
      const maximum = document.documentElement.scrollHeight - window.innerHeight;
      setScrollProgress(maximum > 0 ? Math.min(100, window.scrollY / maximum * 100) : 0);
    };
    window.addEventListener('scroll', updateScroll, { passive: true });
    window.addEventListener('resize', updateScroll);
    updateScroll();
    return () => {
      window.removeEventListener('scroll', updateScroll);
      window.removeEventListener('resize', updateScroll);
    };
  }, []);

  useEffect(() => {
    if (!selectedAnnouncement) return;
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSelectedAnnouncement(null);
    };
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [selectedAnnouncement]);

  useEffect(() => {
    const query = searchQuery.trim();
    if (role !== 'super_admin' || query.length < 2) {
      setSearchResults([]);
      setIsSearchLoading(false);
      setSearchError(null);
      return;
    }

    const controller = new AbortController();
    setSearchResults([]);
    setSearchError(null);
    setIsSearchLoading(true);
    const timeoutId = window.setTimeout(async () => {
      try {
        const params = new URLSearchParams({ search: query });
        const response = await apiFetch(`/api/admin/users?${params}`, { signal: controller.signal });
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.error || 'Failed to search users');
        }

        const users = Array.isArray(data.users) ? data.users : [];
        setSearchResults(users.slice(0, 6).filter(isUserSearchResult));
      } catch (error) {
        if (!controller.signal.aborted) {
          setSearchResults([]);
          setSearchError(error instanceof Error ? error.message : 'Failed to search users');
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsSearchLoading(false);
        }
      }
    }, 220);

    return () => {
      window.clearTimeout(timeoutId);
      controller.abort();
    };
  }, [role, searchQuery]);

  useEffect(() => {
    let isMounted = true;
    const fetchNotifications = async () => {
      try {
        const res = await apiFetch('/api/notifications');
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed to load notifications');
        if (isMounted) {
          const items = Array.isArray(data.items) ? data.items : [];
          setNotifications(items);
          const total = typeof data.totalCount === 'number'
            ? data.totalCount
            : items.reduce((sum: number, item: any) => sum + (item.count || 0), 0);
          setUnreadCount(total);
        }
      } catch {
        if (isMounted) {
          setNotifications([]);
          setUnreadCount(0);
        }
      }
    };
    fetchNotifications();
    const id = setInterval(fetchNotifications, 15000);
    return () => {
      isMounted = false;
      clearInterval(id);
    };
  }, []);

  const toggleNotifications = () => {
    const willOpen = !showNotifications;
    setShowNotifications(willOpen);
    if (!willOpen) return;

    setUnreadCount(0);
    apiFetch('/api/notifications/read', { method: 'POST' }).catch(() => undefined);
  };

  return (
    <div className="admin-portal min-h-screen bg-white flex">
      <div className="fixed inset-x-0 top-0 z-[100] h-1 bg-transparent" aria-hidden="true">
        <div className="h-full bg-gradient-to-r from-blue-600 to-teal-500" style={{ width: `${scrollProgress}%` }} />
      </div>
      {/* Sidebar - Desktop */}
      <aside className="hidden md:flex flex-col w-[232px] bg-white border-r border-neutral-200 sticky top-0 h-screen z-30">
        <div className="p-6 border-b border-neutral-100">
          <div className="flex items-center gap-2">
            <div className="w-[34px] h-[34px] bg-neutral-900 rounded-xl flex items-center justify-center">
              <Shield className="w-5 h-5 text-white" />
            </div>
            <span className="min-w-0"><strong className="block truncate text-sm text-neutral-900">{role === 'super_admin' ? schoolName : panelLabels[role]}</strong>{role === 'super_admin' && <small className="block text-xs text-neutral-500">Super Admin Panel</small>}</span>
          </div>
        </div>

        <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
          {navItems.map((item) => (
            <button
              key={item.id}
              onClick={() => onNavigate(item.id)}
              className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors ${
                currentView === item.id
                  ? 'bg-neutral-900 text-white'
                  : 'text-neutral-600 hover:bg-neutral-100'
              }`}
            >
              <item.icon className="w-5 h-5" />
              {item.label}
            </button>
          ))}
        </nav>

        <div className="p-4 border-t border-neutral-100">
          <button
            onClick={onLogout}
            className="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50 transition-colors"
          >
            <LogOut className="w-5 h-5" />
            Sign Out
          </button>
        </div>
      </aside>

      {/* Mobile Sidebar Overlay */}
      {isSidebarOpen && (
        <div 
          className="fixed inset-0 bg-black/50 z-40 md:hidden"
          onClick={() => setIsSidebarOpen(false)}
        />
      )}

      {/* Mobile Sidebar */}
      <aside className={`fixed inset-y-0 left-0 w-[232px] bg-white border-r border-neutral-200 z-50 transform transition-transform duration-200 ease-in-out md:hidden ${
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
      }`}>
        <div className="p-6 border-b border-neutral-100 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-[34px] h-[34px] bg-neutral-900 rounded-xl flex items-center justify-center">
              <Shield className="w-5 h-5 text-white" />
            </div>
            <span className="min-w-0"><strong className="block truncate text-sm text-neutral-900">{role === 'super_admin' ? schoolName : panelLabels[role]}</strong>{role === 'super_admin' && <small className="block text-xs text-neutral-500">Super Admin Panel</small>}</span>
          </div>
          <button onClick={() => setIsSidebarOpen(false)}>
            <X className="w-6 h-6 text-neutral-500" />
          </button>
        </div>

        <nav className="flex-1 p-4 space-y-1">
          {navItems.map((item) => (
            <button
              key={item.id}
              onClick={() => {
                onNavigate(item.id);
                setIsSidebarOpen(false);
              }}
              className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors ${
                currentView === item.id
                  ? 'bg-neutral-900 text-white'
                  : 'text-neutral-600 hover:bg-neutral-100'
              }`}
            >
              <item.icon className="w-5 h-5" />
              {item.label}
            </button>
          ))}
        </nav>

        <div className="p-4 border-t border-neutral-100">
          <button
            onClick={onLogout}
            className="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50 transition-colors"
          >
            <LogOut className="w-5 h-5" />
            Sign Out
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 min-w-0 flex flex-col min-h-screen">
        {/* Header */}
        <header className="h-16 bg-white border-b border-neutral-200 sticky top-0 z-20 flex items-center justify-between px-4 md:px-8">
          <div className="flex items-center gap-4">
            <button 
              className="md:hidden p-2 -ml-2 text-neutral-600 hover:bg-neutral-100 rounded-lg"
              onClick={() => setIsSidebarOpen(true)}
            >
              <Menu className="w-6 h-6" />
            </button>
            {role === 'super_admin' && (
              <div className="hidden md:flex relative w-64">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                <input
                  type="search"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                  onFocus={() => setIsSearchOpen(true)}
                  onBlur={() => setIsSearchOpen(false)}
                  onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                      setIsSearchOpen(false);
                    }
                  }}
                  aria-label="Search users"
                  aria-expanded={isSearchOpen && searchQuery.trim().length >= 2}
                  aria-controls="global-user-search-results"
                  placeholder="Search users..."
                  className="w-full pl-10 pr-4 py-2 rounded-lg border border-neutral-200 text-sm focus:outline-none focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900"
                />
                {isSearchOpen && searchQuery.trim().length >= 2 && (
                  <div
                    id="global-user-search-results"
                    role="listbox"
                    className="absolute left-0 top-full z-50 mt-2 max-h-80 w-80 overflow-y-auto border border-neutral-200 bg-white shadow-lg"
                  >
                    {isSearchLoading && (
                      <p className="px-4 py-3 text-sm text-neutral-500" role="status">Searching users...</p>
                    )}
                    {searchError && (
                      <p className="px-4 py-3 text-sm text-red-700" role="alert">{searchError}</p>
                    )}
                    {!isSearchLoading && !searchError && searchResults.length === 0 && (
                      <p className="px-4 py-3 text-sm text-neutral-500">No users found.</p>
                    )}
                    {!searchError && searchResults.map((user) => (
                      <button
                        key={user.email}
                        type="button"
                        role="option"
                        aria-selected="false"
                        className="block w-full border-b border-neutral-100 px-4 py-3 text-left hover:bg-neutral-50"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => {
                          setIsSearchOpen(false);
                          navigate(`/super-admin/users?search=${encodeURIComponent(user.email)}`);
                        }}
                      >
                        <span className="block text-sm font-medium text-neutral-900">{user.name}</span>
                        <span className="block text-xs text-neutral-500">{user.email}</span>
                        <span className="block text-xs text-neutral-500">{user.roleLabel || user.role}</span>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="flex items-center gap-4">
            <div className="relative">
              <button
                type="button"
                className="relative p-2 text-neutral-600 hover:bg-neutral-100 rounded-lg transition-colors"
                onClick={toggleNotifications}
              >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                  <span className="portal-notification-badge">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </button>
              {showNotifications && (
                <div className="absolute right-0 mt-2 w-64 bg-white border border-neutral-200 rounded-lg shadow-lg z-30">
                  <div className="px-3 py-2 border-b border-neutral-100 text-xs font-medium text-neutral-500">
                    Notifications
                  </div>
                  <div className="max-h-64 overflow-y-auto text-sm">
                    {notifications.length === 0 ? (
                      <div className="px-3 py-3 text-neutral-500 text-xs">No notifications.</div>
                    ) : (
                      notifications.map((item) => (
                        <button
                          key={item.id}
                          type="button"
                          className="w-full text-left px-3 py-2 hover:bg-neutral-50 flex items-center justify-between gap-2"
                          onClick={() => {
                            if (item.body) {
                              setSelectedAnnouncement(item);
                            } else if (item.targetView) {
                              onNavigate(item.targetView);
                            }
                            setShowNotifications(false);
                          }}
                        >
                          <span className="text-xs text-neutral-700">{item.label}</span>
                          {item.count > 0 && (
                            <span className="ml-2 inline-flex items-center rounded-full bg-neutral-900 text-white text-[10px] px-2 py-0.5">
                              {item.count}
                            </span>
                          )}
                        </button>
                      ))
                    )}
                  </div>
                </div>
              )}
            </div>
            <div className="h-8 w-px bg-neutral-200 mx-1"></div>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button className="flex items-center gap-3 hover:bg-neutral-50 p-2 rounded-lg transition-colors outline-none">
                  <div className="text-right hidden md:block">
                    <p className="text-sm font-medium text-neutral-900 truncate max-w-[180px]" title={userEmail}>
                      {userEmail || roleLabels[role]}
                    </p>
                    <p className="text-xs text-neutral-500">{roleLabels[role]}</p>
                  </div>
                  <Avatar>
                    <AvatarImage src="https://github.com/shadcn.png" />
                    <AvatarFallback>AD</AvatarFallback>
                  </Avatar>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>My Account</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => onNavigate('settings')}>
                  Settings
                </DropdownMenuItem>
                <DropdownMenuItem>Support</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-red-600" onClick={onLogout}>
                  Log out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        {/* Page Content */}
        <div key={currentView} className="portal-content portal-view-enter p-4 md:p-8 flex-1 overflow-x-hidden">
          {children}
        </div>
      </main>
      {selectedAnnouncement && (
        <div className="announcement-modal-overlay" role="presentation" onMouseDown={event => {
          if (event.target === event.currentTarget) setSelectedAnnouncement(null);
        }}>
          <section className="announcement-modal" role="dialog" aria-modal="true" aria-labelledby="announcement-modal-title">
            <header>
              <div>
                <p className="announcement-modal-kicker">Announcement</p>
                <h2 id="announcement-modal-title">{selectedAnnouncement.title}</h2>
              </div>
              <button type="button" className="announcement-modal-close" aria-label="Close announcement" onClick={() => setSelectedAnnouncement(null)}>
                <X className="h-5 w-5" />
              </button>
            </header>
            <p className="announcement-modal-detail">{selectedAnnouncement.detail}</p>
            <div className="announcement-modal-body">{selectedAnnouncement.body}</div>
            {selectedAnnouncement.timestamp && (
              <time className="announcement-modal-time" dateTime={selectedAnnouncement.timestamp}>
                Published {new Date(selectedAnnouncement.timestamp).toLocaleString()}
              </time>
            )}
            <footer>
              <button type="button" className="announcement-modal-done" onClick={() => setSelectedAnnouncement(null)}>Close</button>
            </footer>
          </section>
        </div>
      )}
      {!cookieAccepted && (
        <div className="fixed bottom-4 left-4 right-4 md:left-auto md:max-w-lg z-50 rounded-xl border border-neutral-200 bg-white p-4 shadow-2xl flex items-center gap-4">
          <div><p className="text-sm font-medium text-neutral-900">Essential cookies</p><p className="text-xs text-neutral-600 mt-1">Used for secure authentication and saved display preferences.</p></div>
          <button className="shrink-0 rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white hover:bg-neutral-700" onClick={() => { localStorage.setItem('staff-cookie-notice', 'accepted'); setCookieAccepted(true); }}>Got it</button>
        </div>
      )}
    </div>
  );
};
