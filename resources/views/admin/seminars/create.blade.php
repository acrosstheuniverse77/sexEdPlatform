@extends('layouts.admin')
@section('title', 'Create Seminar')
@section('page-title', 'Create Seminar')
@section('content')

<div class="mb-5">
 <a href="{{ route('admin.seminars.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 transition-colors">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
 Back to Seminars
 </a>
</div>

<div class="max-w-2xl">
 <div class="rounded-2xl bg-white border border-gray-200 shadow-theme-xs overflow-hidden">
 <div class="px-6 py-4 border-b border-gray-100 ">
 <h3 class="text-base font-semibold text-gray-900 ">Seminar Details</h3>
 </div>
 <form class="p-6 space-y-5" method="POST" action="#" onsubmit="alert('Backend not yet implemented'); return false;">
 @csrf
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Title</label>
 <input type="text" name="title" placeholder="Seminar title..."
 class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
 <textarea name="description" rows="3" placeholder="Brief description..."
 class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 resize-none transition"></textarea>
 </div>
 <div class="grid grid-cols-2 gap-4">
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
 <input type="date" name="date" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Time</label>
 <input type="time" name="time" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
 </div>
 </div>
 <div class="grid grid-cols-2 gap-4">
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
 <select name="type" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-white text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
 <option value="online">Online</option>
 <option value="in_person">In-Person</option>
 <option value="hybrid">Hybrid</option>
 </select>
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Capacity</label>
 <input type="number" name="capacity" min="1" placeholder="e.g. 100"
 class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
 </div>
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Presenter / Speaker</label>
 <input type="text" name="presenter" placeholder="Dr. / Prof. Name..."
 class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Meeting Link / Venue</label>
 <input type="text" name="location" placeholder="Zoom link or venue address..."
 class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1.5">Target Audience</label>
 <div class="flex flex-wrap gap-3">
 @foreach(['Learners','Instructors','Counselors','Guardians','Healthcare Workers','General Public'] as $aud)
 <label class="flex items-center gap-2 text-sm text-gray-700 ">
 <input type="checkbox" name="audience[]" value="{{ strtolower($aud) }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
 {{ $aud }}
 </label>
 @endforeach
 </div>
 </div>
 <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 ">
 <a href="{{ route('admin.seminars.index') }}" class="px-4 py-2 rounded-lg text-sm text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">Cancel</a>
 <button type="submit" class="px-6 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium shadow-theme-xs transition-colors">Create Seminar</button>
 </div>
 </form>
 </div>
</div>
@endsection
