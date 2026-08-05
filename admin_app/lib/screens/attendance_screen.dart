import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../providers/app_state.dart';
import 'home_shell.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  final _nameCtrl = TextEditingController();
  final _empIdCtrl = TextEditingController();
  String _nameQuery = '';
  String _empIdQuery = '';

  @override
  void dispose() {
    _nameCtrl.dispose();
    _empIdCtrl.dispose();
    super.dispose();
  }

  void _applySearch() {
    setState(() {
      _nameQuery = _nameCtrl.text.trim().toLowerCase();
      _empIdQuery = _empIdCtrl.text.trim().toLowerCase();
    });
  }

  void _clearSearch() {
    _nameCtrl.clear();
    _empIdCtrl.clear();
    setState(() {
      _nameQuery = '';
      _empIdQuery = '';
    });
  }

  List<AttendanceRow> _filtered(List<AttendanceRow> rows) {
    return rows.where((e) {
      final nameOk = _nameQuery.isEmpty || e.name.toLowerCase().contains(_nameQuery);
      final empNo = (e.employeeNo ?? '').toLowerCase();
      final idOk = _empIdQuery.isEmpty || empNo.contains(_empIdQuery);
      return nameOk && idOk;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final filtered = _filtered(state.attendance);
    final monthLabel = state.attendanceMonthLabel.isEmpty ? 'Current Month' : state.attendanceMonthLabel;

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
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
                    ),
                  _SearchPanel(
                    nameCtrl: _nameCtrl,
                    empIdCtrl: _empIdCtrl,
                    onSearch: _applySearch,
                    onClear: _clearSearch,
                  ),
                  const SizedBox(height: 12),
                  _SectionHeader(title: monthLabel, subtitle: 'Monthly summary'),
                  const SizedBox(height: 8),
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: Text('No employees found.', style: TextStyle(color: Colors.white54))),
                    )
                  else
                    ...filtered.map((e) => _MonthEmployeeCard(employee: e)),
                  const SizedBox(height: 20),
                  _SectionHeader(
                    title: "Today's Attendance",
                    subtitle: state.attendanceDate.isEmpty ? 'Today' : state.attendanceDate,
                  ),
                  const SizedBox(height: 8),
                  Card(
                    color: const Color(0xFF151C2C),
                    child: ListTile(
                      title: const Text('Summary', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                      subtitle: Text(
                        'Present ${state.attendancePresent} · Absent ${state.attendanceAbsent}',
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: Text('No attendance for today.', style: TextStyle(color: Colors.white54))),
                    )
                  else
                    ...filtered.map((e) => _TodayEmployeeCard(employee: e)),
                  const SizedBox(height: 12),
                ],
              ),
      ),
    );
  }
}

class _SearchPanel extends StatelessWidget {
  const _SearchPanel({
    required this.nameCtrl,
    required this.empIdCtrl,
    required this.onSearch,
    required this.onClear,
  });

  final TextEditingController nameCtrl;
  final TextEditingController empIdCtrl;
  final VoidCallback onSearch;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: const Color(0xFF151C2C),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            TextField(
              controller: nameCtrl,
              style: const TextStyle(color: Colors.white),
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Search by name',
                labelStyle: TextStyle(color: Colors.white54),
                prefixIcon: Icon(Icons.person_search, color: Colors.white54),
                enabledBorder: OutlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                focusedBorder: OutlineInputBorder(borderSide: BorderSide(color: Color(0xFF7C3AED))),
              ),
              onSubmitted: (_) => onSearch(),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: empIdCtrl,
              style: const TextStyle(color: Colors.white),
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                labelText: 'Employee ID',
                labelStyle: TextStyle(color: Colors.white54),
                prefixIcon: Icon(Icons.badge_outlined, color: Colors.white54),
                enabledBorder: OutlineInputBorder(borderSide: BorderSide(color: Colors.white24)),
                focusedBorder: OutlineInputBorder(borderSide: BorderSide(color: Color(0xFF7C3AED))),
              ),
              onSubmitted: (_) => onSearch(),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onClear,
                    icon: const Icon(Icons.clear, size: 18),
                    label: const Text('Clear'),
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.white70),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: onSearch,
                    icon: const Icon(Icons.search, size: 18),
                    label: const Text('Search'),
                    style: FilledButton.styleFrom(backgroundColor: const Color(0xFF7C3AED)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              Text(subtitle, style: const TextStyle(color: Colors.white54, fontSize: 12)),
            ],
          ),
        ),
      ],
    );
  }
}

class _MonthEmployeeCard extends StatelessWidget {
  const _MonthEmployeeCard({required this.employee});

  final AttendanceRow employee;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: const Color(0xFF151C2C),
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(employee.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15)),
            if ((employee.employeeNo ?? '').isNotEmpty) ...[
              const SizedBox(height: 2),
              Text('ID ${employee.employeeNo}', style: const TextStyle(color: Colors.white54, fontSize: 12)),
            ],
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: _MonthStat(label: 'Present', value: employee.monthPresent, color: const Color(0xFF22C55E))),
                const SizedBox(width: 8),
                Expanded(child: _MonthStat(label: 'Absent', value: employee.monthAbsent, color: const Color(0xFFEF4444))),
                const SizedBox(width: 8),
                Expanded(child: _MonthStat(label: 'Holiday', value: employee.monthHoliday, color: const Color(0xFF3B82F6))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MonthStat extends StatelessWidget {
  const _MonthStat({required this.label, required this.value, required this.color});

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      child: Column(
        children: [
          Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
          const SizedBox(height: 2),
          Text('$value', style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _TodayEmployeeCard extends StatelessWidget {
  const _TodayEmployeeCard({required this.employee});

  final AttendanceRow employee;

  Color _statusColor() {
    if (employee.isPresent) return const Color(0xFF22C55E);
    if (employee.isHoliday) return const Color(0xFF3B82F6);
    return const Color(0xFFEF4444);
  }

  IconData _statusIcon() {
    if (employee.isPresent) return Icons.check_circle;
    if (employee.isHoliday) return Icons.beach_access;
    return Icons.cancel;
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      color: const Color(0xFF151C2C),
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(employee.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        subtitle: Text(
          [
            if ((employee.employeeNo ?? '').isNotEmpty) 'ID ${employee.employeeNo}',
            employee.todayStatusLabel,
            if ((employee.clockIn ?? '').isNotEmpty) 'In ${employee.clockIn}',
            if ((employee.clockOut ?? '').isNotEmpty) 'Out ${employee.clockOut}',
          ].join(' · '),
          style: const TextStyle(color: Colors.white60),
        ),
        trailing: Icon(_statusIcon(), color: _statusColor()),
      ),
    );
  }
}
