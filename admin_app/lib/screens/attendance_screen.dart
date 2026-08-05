import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import 'home_shell.dart';

class AttendanceScreen extends StatelessWidget {
  const AttendanceScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    return AdminDarkScaffold(
      title: 'Attendance',
      body: RefreshIndicator(
        onRefresh: () => state.refreshAttendance(),
        child: state.loading && state.attendance.isEmpty
            ? ListView(children: const [SizedBox(height: 120), Center(child: CircularProgressIndicator())])
            : ListView(
                padding: const EdgeInsets.all(12),
                children: [
                  if (state.error != null && state.attendance.isEmpty)
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
                    ),
                  Card(
                    color: const Color(0xFF151C2C),
                    child: ListTile(
                      title: Text(
                        state.attendanceDate.isEmpty ? 'Today' : state.attendanceDate,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                      ),
                      subtitle: Text(
                        'Present ${state.attendancePresent} · Absent ${state.attendanceAbsent}',
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (state.attendance.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 48),
                      child: Center(child: Text('No employees.', style: TextStyle(color: Colors.white54))),
                    )
                  else
                    ...state.attendance.map((e) {
                      final ok = e.isPresent;
                      return Card(
                        color: const Color(0xFF151C2C),
                        child: ListTile(
                          title: Text(e.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                          subtitle: Text(
                            [
                              if ((e.employeeNo ?? '').isNotEmpty) '#${e.employeeNo}',
                              e.status,
                              if ((e.clockIn ?? '').isNotEmpty) 'In ${e.clockIn}',
                              if ((e.clockOut ?? '').isNotEmpty) 'Out ${e.clockOut}',
                            ].join(' · '),
                            style: const TextStyle(color: Colors.white60),
                          ),
                          trailing: Icon(
                            ok ? Icons.check_circle : Icons.cancel,
                            color: ok ? const Color(0xFF22C55E) : const Color(0xFFEF4444),
                          ),
                        ),
                      );
                    }),
                ],
              ),
      ),
    );
  }
}
