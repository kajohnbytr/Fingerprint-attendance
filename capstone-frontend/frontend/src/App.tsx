import React, { Component, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { LoginForm } from './components/LoginForm';
import { StaffPortalAccessGate } from './components/StaffPortalAccessGate';
import { ForgotPasswordForm } from './components/ForgotPasswordForm';
import { ForcePasswordChangeForm } from './components/ForcePasswordChangeForm';
import { StudentRegistrationForm } from './components/StudentRegistrationForm';
import { BusinessModelCanvas } from './components/BusinessModelCanvas';
import { NotFound } from './components/NotFound';
import {
  AppReports,
  ArchiveRequests,
  AuditLogs,
  DashboardLayout,
  Overview,
  Reports,
  Schedule,
  MasterSchedule,
  Settings,
  UserManagement,
} from './components/dashboard';
import type { UserRole } from './types/rbac';
import {
  LayoutDashboard,
  Users,
  Settings as SettingsIcon,
  FileText,
  Shield,
  ClipboardList,
  MessageSquareWarning,
  CalendarDays,
  TableProperties,
  ShieldAlert,
  LogOut,
} from 'lucide-react';

import { apiFetch, clearApiToken, hasApiToken } from './api';

type NavItem = {
  id: string;
  label: string;
  icon: any;
};

type ViewRoute = {
  id: string;
  path: string;
  element: any;
};

type IdleSettings = {
  timeoutMinutes: number;
  warningDelaySeconds: number;
  countdownSeconds: number;
};

const idleSettingsFromMinutes = (minutes: number): IdleSettings => {
  const timeoutMinutes = Math.min(1440, Math.max(5, minutes));
  const totalSeconds = timeoutMinutes * 60;
  const countdownSeconds = Math.min(120, totalSeconds - 60);

  return {
    timeoutMinutes,
    warningDelaySeconds: totalSeconds - countdownSeconds,
    countdownSeconds,
  };
};

type StaffRole = Extract<UserRole, 'admin' | 'super_admin'>;

class RegistrationErrorBoundary extends Component<
  { children: React.ReactNode },
  { hasError: boolean }
> {
  state = { hasError: false };

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error: Error) {
    console.error('Registration form error:', error);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="w-full max-w-md bg-white rounded-2xl shadow-sm border border-neutral-200 p-8 text-center space-y-4">
          <h1 className="text-xl font-semibold text-neutral-900">Something went wrong</h1>
          <p className="text-neutral-500 text-sm">
            The registration page could not load. Please check your connection and try again, or use the link from the same device where you received it.
          </p>
          <a
            href="/register/student"
            className="inline-block w-full py-3 rounded-lg bg-neutral-900 text-white font-medium hover:bg-neutral-800 text-center"
          >
            Try again
          </a>
        </div>
      );
    }
    return this.props.children;
  }
}

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [authView, setAuthView] = useState<'login' | 'forgot'>('login');
  const [role, setRole] = useState<StaffRole>('admin');
  const [userEmail, setUserEmail] = useState<string>('');
  const [mustChangePassword, setMustChangePassword] = useState(false);
  const [currentView, setCurrentView] = useState('overview');
  const [isMaintenanceMode, setIsMaintenanceMode] = useState(false);
  const [isLoadingAuth, setIsLoadingAuth] = useState(true);
  const idleWarningTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const idleCountdownTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const idleWarningOpenRef = useRef(false);
  const [showIdleWarning, setShowIdleWarning] = useState(false);
  const [idleSettings, setIdleSettings] = useState<IdleSettings>(() => idleSettingsFromMinutes(30));
  const [idleSecondsRemaining, setIdleSecondsRemaining] = useState(120);
  const navigate = useNavigate();
  const location = useLocation();
  const isRegistrationRoute = location.pathname === '/register/student' || location.pathname.startsWith('/register/student/');

  useEffect(() => {
    const showLockdown = () => setIsMaintenanceMode(true);
    window.addEventListener('system-lockdown', showLockdown);
    return () => window.removeEventListener('system-lockdown', showLockdown);
  }, []);

  // Check for existing session on app load (skip on public pages to avoid 401 noise)
  useEffect(() => {
    const isPublicRoute =
      isRegistrationRoute ||
      location.pathname === '/login/admin' ||
      location.pathname === '/login/super-admin' ||
      location.pathname.startsWith('/login/');

    if (isPublicRoute) {
      setIsLoadingAuth(false);
      return;
    }

    if (!hasApiToken()) {
      setIsLoadingAuth(false);
      return;
    }

    const checkAuth = async () => {
      try {
        const response = await apiFetch('/api/auth/me');

        if (response.ok) {
          const data = await response.json();
          if (data.user) {
            if (data.user.role !== 'admin' && data.user.role !== 'super_admin') {
              await apiFetch('/api/auth/logout', { method: 'POST' });
              navigate('/login/admin', { replace: true });
              return;
            }

            const staffRole = data.user.role as StaffRole;
            setRole(staffRole);
            setUserEmail(data.user.email ?? '');
            setMustChangePassword(!!data.user.mustChangePassword);
            setCurrentView(navItemsByRole[staffRole][0]?.id ?? 'overview');
            setIsAuthenticated(true);
            const defaultPath = viewRoutesByRole[staffRole][0]?.path ?? '/';
            if (
              location.pathname === '/' ||
              location.pathname === '/login/admin' ||
              location.pathname === '/login/super-admin'
            ) {
              navigate(defaultPath, { replace: true });
            }
          }
        }
      } catch (error) {
        console.error('Auth check failed:', error);
      } finally {
        setIsLoadingAuth(false);
      }
    };

    checkAuth();
  }, []);

  const handleLogout = useCallback(async () => {
    try {
      await apiFetch('/api/auth/logout', {
        method: 'POST',
      });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      clearApiToken();
      setIsAuthenticated(false);
      setRole('admin');
      setUserEmail('');
      setMustChangePassword(false);
      navigate('/');
    }
  }, [navigate]);

  useEffect(() => {
    if (!isAuthenticated || mustChangePassword) return;
    const controller = new AbortController();

    apiFetch('/api/session-settings', { signal: controller.signal })
      .then(async response => {
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Unable to load inactivity settings.');
        if (body.settings) setIdleSettings(body.settings);
      })
      .catch(error => {
        if (error.name !== 'AbortError') console.error('Idle settings error:', error);
      });

    const updateIdleSettings = (event: Event) => {
      const settings = (event as CustomEvent<{ sessionTimeoutMinutes?: number }>).detail;
      if (Number.isFinite(settings?.sessionTimeoutMinutes)) {
        setIdleSettings(idleSettingsFromMinutes(Number(settings.sessionTimeoutMinutes)));
      }
    };
    window.addEventListener('school-settings-updated', updateIdleSettings);

    return () => {
      controller.abort();
      window.removeEventListener('school-settings-updated', updateIdleSettings);
    };
  }, [isAuthenticated, mustChangePassword]);

  const continueIdleSession = useCallback(() => {
    idleWarningOpenRef.current = false;
    setShowIdleWarning(false);
    setIdleSecondsRemaining(idleSettings.countdownSeconds);
    if (idleCountdownTimerRef.current) clearInterval(idleCountdownTimerRef.current);
    window.dispatchEvent(new Event('portal-session-continued'));
  }, [idleSettings.countdownSeconds]);

  useEffect(() => {
    if (!isAuthenticated || mustChangePassword) return;
    const warningDelayMs = idleSettings.warningDelaySeconds * 1000;
    const scheduleWarning = () => {
      if (idleWarningTimerRef.current) clearTimeout(idleWarningTimerRef.current);
      idleWarningTimerRef.current = setTimeout(() => {
        idleWarningOpenRef.current = true;
        setShowIdleWarning(true);
        setIdleSecondsRemaining(idleSettings.countdownSeconds);
        if (idleCountdownTimerRef.current) clearInterval(idleCountdownTimerRef.current);
        idleCountdownTimerRef.current = setInterval(() => {
          setIdleSecondsRemaining(previous => {
            if (previous <= 1) {
              if (idleCountdownTimerRef.current) clearInterval(idleCountdownTimerRef.current);
              handleLogout();
              return 0;
            }
            return previous - 1;
          });
        }, 1000);
      }, warningDelayMs);
    };
    const recordActivity = () => {
      if (!idleWarningOpenRef.current) scheduleWarning();
    };
    const continueListener = () => scheduleWarning();
    const events = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'input'];
    events.forEach(eventName => window.addEventListener(eventName, recordActivity, { passive: true }));
    window.addEventListener('portal-session-continued', continueListener);
    scheduleWarning();

    return () => {
      events.forEach(eventName => window.removeEventListener(eventName, recordActivity));
      window.removeEventListener('portal-session-continued', continueListener);
      if (idleWarningTimerRef.current) clearTimeout(idleWarningTimerRef.current);
      if (idleCountdownTimerRef.current) clearInterval(idleCountdownTimerRef.current);
    };
  }, [isAuthenticated, mustChangePassword, handleLogout, idleSettings]);

  const navItemsByRole: Record<StaffRole, NavItem[]> = useMemo(
    () => ({
      admin: [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'schedule', label: 'Class Schedule', icon: CalendarDays },
        { id: 'reports', label: 'Attendance Reports', icon: FileText },
        { id: 'archive-requests', label: 'Archive Requests', icon: ClipboardList },
      ],
      super_admin: [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'master-schedule', label: 'Master Schedule', icon: TableProperties },
        { id: 'users', label: 'User Management', icon: Users },
        { id: 'archive-requests', label: 'Archive Requests', icon: ClipboardList },
        { id: 'settings', label: 'System Configuration', icon: SettingsIcon },
        { id: 'logs', label: 'Audit Logs', icon: Shield },
        { id: 'app-reports', label: 'App Reports', icon: MessageSquareWarning },
      ],
    }),
    []
  );

  const viewRoutesByRole = useMemo<Record<StaffRole, ViewRoute[]>>(
    () => ({
      admin: [
        { id: 'overview', path: '/admin/overview', element: <Overview role="admin" /> },
        { id: 'schedule', path: '/admin/schedule', element: <Schedule role="admin" /> },
        { id: 'reports', path: '/admin/reports', element: <Reports role={role} /> },
        {
          id: 'archive-requests',
          path: '/admin/archive-requests',
          element: <ArchiveRequests canReview={false} />,
        },
      ],
      super_admin: [
        { id: 'overview', path: '/super-admin/overview', element: <Overview role="super_admin" /> },
        { id: 'master-schedule', path: '/super-admin/master-schedule', element: <MasterSchedule /> },
        { id: 'users', path: '/super-admin/users', element: <UserManagement /> },
        {
          id: 'archive-requests',
          path: '/super-admin/archive-requests',
          element: <ArchiveRequests canReview />,
        },
        {
          id: 'settings',
          path: '/super-admin/settings',
          element: <Settings />,
        },
        { id: 'logs', path: '/super-admin/logs', element: <AuditLogs /> },
        { id: 'app-reports', path: '/super-admin/app-reports', element: <AppReports mode="review" /> },
      ],
    }),
    [role]
  );

  const allowedViews = useMemo(
    () => new Set(viewRoutesByRole[role].map((r) => r.id)),
    [viewRoutesByRole, role]
  );

  const defaultPath = viewRoutesByRole[role][0]?.path ?? '/';

  useEffect(() => {
    if (!isAuthenticated || isLoadingAuth) {
      return;
    }
    if (isRegistrationRoute) {
      return;
    }
    const roleRoutes = viewRoutesByRole[role];
    const matchingRoute = roleRoutes.find((route) => route.path === location.pathname);
    if (matchingRoute) {
      if (matchingRoute.id !== currentView) {
        setCurrentView(matchingRoute.id);
      }
      return;
    }
    if (location.pathname !== defaultPath) {
      navigate(defaultPath, { replace: true });
    }
  }, [currentView, defaultPath, isAuthenticated, isLoadingAuth, location.pathname, navigate, role, viewRoutesByRole]);

  useEffect(() => {
    if (!allowedViews.has(currentView)) {
      setCurrentView(navItemsByRole[role][0]?.id ?? 'overview');
    }
  }, [allowedViews, currentView, navItemsByRole, role]);

  const handleNavigate = (viewId: string) => {
    const match = viewRoutesByRole[role].find((route) => route.id === viewId);
    if (!match) {
      return;
    }
    setCurrentView(viewId);
    navigate(match.path);
  };

  if (isRegistrationRoute) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-neutral-100 via-neutral-50 to-neutral-100 flex items-center justify-center p-4 relative">
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-gradient-to-br from-neutral-200/40 to-transparent blur-3xl"></div>
          <div className="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-gradient-to-tr from-neutral-300/30 to-transparent blur-3xl"></div>
          <div className="absolute top-1/3 right-1/4 w-64 h-64 rounded-full bg-neutral-200/20 blur-2xl"></div>
          <div
            className="absolute inset-0 opacity-[0.02]"
            style={{
              width: '24px',
              height: '24px',
              backgroundImage: `radial-gradient(circle, #000 1px, transparent 1px)`,
              backgroundSize: '24px 24px',
            }}
          ></div>
        </div>
        <div className="relative z-10 w-full max-w-4xl flex justify-center">
          <RegistrationErrorBoundary>
            <StudentRegistrationForm />
          </RegistrationErrorBoundary>
        </div>
      </div>
    );
  }

  if (isLoadingAuth) {
    return (
      <div className="min-h-screen bg-neutral-50 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-900"></div>
          <p className="mt-4 text-neutral-600">Loading...</p>
        </div>
      </div>
    );
  }

  if (isAuthenticated) {
    if (mustChangePassword) {
      return <ForcePasswordChangeForm onChanged={() => setMustChangePassword(false)} />;
    }

    if (isMaintenanceMode && role !== 'super_admin') {
      return (
        <main className="portal-lockdown-screen">
          <div className="portal-lockdown-card" role="alert" aria-live="assertive">
            <div className="portal-lockdown-icon" aria-hidden="true"><ShieldAlert /></div>
            <span className="portal-lockdown-kicker">Emergency security mode</span>
            <h1>System Lockdown</h1>
            <p>
              Access to the attendance system has been temporarily restricted by the Super Administrator due to a security concern or detected anomaly.
            </p>
            <div className="portal-lockdown-notice">
              Your account and attendance data remain protected. Please wait for the Super Administrator to restore access.
            </div>
            <button
              className="portal-lockdown-signout"
              onClick={handleLogout}
            >
              <LogOut aria-hidden="true" />
              Sign out securely
            </button>
          </div>
        </main>
      );
    }
    return (
      <>
      <DashboardLayout
        currentView={currentView}
        onNavigate={handleNavigate}
        onLogout={handleLogout}
        role={role}
        userEmail={userEmail}
        navItems={navItemsByRole[role]}
        children={
          <Routes>
            {viewRoutesByRole[role].map((route) => (
              <Route key={route.path} path={route.path} element={route.element} />
            ))}
            <Route path="/admin" element={<Navigate to={defaultPath} replace />} />
            <Route path="/super-admin" element={<Navigate to={defaultPath} replace />} />
            <Route path="/login/admin" element={<Navigate to={defaultPath} replace />} />
            <Route path="/login/super-admin" element={<Navigate to={defaultPath} replace />} />
            <Route path="/" element={<Navigate to={defaultPath} replace />} />
            <Route 
              path="*" 
              element={
                <NotFound 
                  isAuthenticated={true} 
                  onGoHome={() => navigate(defaultPath)} 
                />
              } 
            />
          </Routes>
        }
      />
      {showIdleWarning && (
        <div className="portal-idle-overlay" role="dialog" aria-modal="true" aria-labelledby="react-idle-title">
          <div className="portal-idle-card">
            <span className="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-neutral-950 text-2xl font-bold text-white" aria-hidden="true">!</span>
            <h2 id="react-idle-title" className="text-2xl font-semibold text-neutral-950">Are you still there?</h2>
            <p className="mt-3 leading-6 text-neutral-600">No activity was detected. For security, you will be signed out automatically in <strong>{Math.floor(idleSecondsRemaining / 60)}:{String(idleSecondsRemaining % 60).padStart(2, '0')}</strong>.</p>
            <button type="button" autoFocus onClick={continueIdleSession} className="portal-idle-continue">I’m still here</button>
          </div>
        </div>
      )}
      </>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-neutral-100 via-neutral-50 to-neutral-100 flex items-center justify-center p-4 relative">
      {/* Minimalist Background Elements */}
      <div className="absolute inset-0 overflow-hidden">
        {/* Large Circle - Top Right */}
        <div className="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-gradient-to-br from-neutral-200/40 to-transparent blur-3xl"></div>
        
        {/* Medium Circle - Bottom Left */}
        <div className="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-gradient-to-tr from-neutral-300/30 to-transparent blur-3xl"></div>
        
        {/* Small Circle - Center */}
        <div className="absolute top-1/3 right-1/4 w-64 h-64 rounded-full bg-neutral-200/20 blur-2xl"></div>
        
        {/* Subtle Grid Pattern */}
        <div className="absolute inset-0 opacity-[0.02]" style={{
          width: '24px',
          height: '24px',
          backgroundImage: `radial-gradient(circle, #000 1px, transparent 1px)`,
          backgroundSize: '24px 24px'
        }}></div>
      </div>

      {/* Content */}
      <div className="relative z-10">
        {authView === 'login' ? (
          <Routes>
            <Route
              path="/"
              element={<Navigate to="/login/admin" replace />}
            />
            <Route
              path="/login/student"
              element={<Navigate to="/login/admin" replace />}
            />
            <Route path="/register/student" element={<StudentRegistrationForm />} />
            <Route path="/activity" element={<BusinessModelCanvas />} />
            <Route
              path="/login/admin"
              element={
                <StaffPortalAccessGate portal="admin">
                  <LoginForm
                    forcedRole="admin"
                    showRoleSelector={false}
                    onLogin={(user) => {
                      setRole(user.role);
                      setUserEmail(user.email ?? '');
                      setMustChangePassword(!!user.mustChangePassword);
                      setCurrentView(navItemsByRole[user.role][0]?.id ?? 'overview');
                      setIsAuthenticated(true);
                      const nextDefault = viewRoutesByRole[user.role][0]?.path ?? '/';
                      navigate(nextDefault);
                    }}
                    onForgot={() => setAuthView('forgot')}
                  />
                </StaffPortalAccessGate>
              }
            />
            <Route
              path="/login/super-admin"
              element={
                <StaffPortalAccessGate portal="super_admin">
                  <LoginForm
                    forcedRole="super_admin"
                    showRoleSelector={false}
                    onLogin={(user) => {
                      setRole(user.role);
                      setUserEmail(user.email ?? '');
                      setMustChangePassword(!!user.mustChangePassword);
                      setCurrentView(navItemsByRole[user.role][0]?.id ?? 'overview');
                      setIsAuthenticated(true);
                      const nextDefault = viewRoutesByRole[user.role][0]?.path ?? '/';
                      navigate(nextDefault);
                    }}
                  />
                </StaffPortalAccessGate>
              }
            />
            {/* Redirect protected paths to the right login when not authenticated */}
            <Route path="/super-admin/*" element={<Navigate to="/login/super-admin" replace />} />
            <Route path="/login/program-head" element={<Navigate to="/login/admin" replace />} />
            <Route path="/program-head/*" element={<Navigate to="/login/admin" replace />} />
            <Route path="/admin/*" element={<Navigate to="/login/admin" replace />} />
            <Route path="/student/*" element={<Navigate to="/login/admin" replace />} />
            <Route path="*" element={<NotFound isAuthenticated={false} />} />
          </Routes>
        ) : authView === 'forgot' ? (
          <ForgotPasswordForm onBack={() => setAuthView('login')} />
        ) : (
          <ForgotPasswordForm onBack={() => setAuthView('login')} />
        )}
      </div>
    </div>
  );
}
